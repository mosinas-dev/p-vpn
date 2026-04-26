<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Models\VpnKey;
use App\Services\Keys\Exceptions\KeyAlreadyIssuedException;
use App\Services\Keys\Exceptions\NoActiveSubscriptionException;
use App\Services\Keys\Exceptions\UnknownLocationException;
use App\Services\Keys\KeyProvisioningService;
use App\Services\Panel\DTO\ServerInfo;
use App\Services\Panel\ServerCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class KeyProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private KeyProvisioningService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Mail::fake();
        config([
            'panel.base_url' => 'https://panel.test',
            'panel.jwt_token' => 'jwt',
        ]);
        $this->service = $this->app->make(KeyProvisioningService::class);
    }

    private function fakePanel(array $overrides = []): void
    {
        $defaults = [
            'panel.test/api/servers' => Http::response([
                ['id' => 1, 'name' => 'Нидерланды', 'status' => 'active', 'clients_count' => 5],
                ['id' => 2, 'name' => 'Германия', 'status' => 'active', 'clients_count' => 12],
                ['id' => 9, 'name' => 'Disabled', 'status' => 'inactive', 'clients_count' => 0],
            ], 200),
            'panel.test/api/clients/create' => Http::response([
                'id' => 777, 'config' => '[Interface]...', 'qr_code' => 'data:image/png;base64,A=',
            ], 200),
            'panel.test/api/clients/777/revoke' => Http::response(['ok' => true], 200),
            'panel.test/api/clients/777/restore' => Http::response(['ok' => true], 200),
        ];
        Http::fake(array_merge($defaults, $overrides));
        Cache::flush();
    }

    private function activeUserWithSubscription(): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $sub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'months' => 1,
            'price_kopecks' => 20000,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);
        return [$user, $sub];
    }

    public function test_issue_creates_key_in_panel_and_saves_locally(): void
    {
        [$user, $sub] = $this->activeUserWithSubscription();
        $this->fakePanel();

        $key = $this->service->issue($sub, 1);

        $this->assertSame(1, $key->panel_server_id);
        $this->assertSame(777, $key->panel_client_id);
        $this->assertSame(VpnKey::STATUS_ACTIVE, $key->status);
        $this->assertSame($user->id, $key->user_id);
        $this->assertSame($sub->id, $key->subscription_id);
        $this->assertStringStartsWith('[Interface]', $key->config_text);
    }

    public function test_issue_rejects_unknown_location(): void
    {
        [, $sub] = $this->activeUserWithSubscription();
        $this->fakePanel();

        $this->expectException(UnknownLocationException::class);
        $this->service->issue($sub, 999);
    }

    public function test_issue_rejects_inactive_location(): void
    {
        [, $sub] = $this->activeUserWithSubscription();
        $this->fakePanel();

        $this->expectException(UnknownLocationException::class);
        $this->service->issue($sub, 9);
    }

    public function test_issue_rejects_when_subscription_not_active(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $sub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
            'months' => 1,
            'price_kopecks' => 20000,
        ]);

        $this->expectException(NoActiveSubscriptionException::class);
        $this->service->issue($sub, 1);
    }

    public function test_issue_rejects_when_subscription_already_has_active_key(): void
    {
        [$user, $sub] = $this->activeUserWithSubscription();
        $this->fakePanel();
        VpnKey::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'panel_server_id' => 1,
            'panel_client_id' => 100,
            'name' => 'existing',
            'status' => VpnKey::STATUS_ACTIVE,
        ]);

        $this->expectException(KeyAlreadyIssuedException::class);
        $this->service->issue($sub, 1);
    }

    public function test_change_location_revokes_old_and_creates_new_in_same_record(): void
    {
        [$user, $sub] = $this->activeUserWithSubscription();
        $key = VpnKey::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'panel_server_id' => 1,
            'panel_client_id' => 777,
            'name' => 'old',
            'status' => VpnKey::STATUS_ACTIVE,
            'config_text' => '[Interface] old',
        ]);

        // полная замена fakes: новый клиент создаётся с id=888
        $this->fakePanel([
            'panel.test/api/clients/create' => Http::response([
                'id' => 888, 'config' => '[Interface] new', 'qr_code' => 'data:image/png;base64,B=',
            ], 200),
        ]);

        $updated = $this->service->changeLocation($key, 2);

        $this->assertSame($key->id, $updated->id, 'та же запись vpn_keys, обновлена in-place');
        $this->assertSame(2, $updated->panel_server_id);
        $this->assertSame(888, $updated->panel_client_id);
        $this->assertSame(VpnKey::STATUS_ACTIVE, $updated->status);
        $this->assertStringContainsString('new', $updated->config_text);

        // Старый клиент в panel должен быть revoked
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/clients/777/revoke'));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/clients/create'));
    }

    public function test_change_location_to_same_location_is_noop(): void
    {
        [$user, $sub] = $this->activeUserWithSubscription();
        $this->fakePanel();
        $key = VpnKey::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'panel_server_id' => 1,
            'panel_client_id' => 777,
            'name' => 'same',
            'status' => VpnKey::STATUS_ACTIVE,
            'config_text' => '[Interface] same',
        ]);

        $result = $this->service->changeLocation($key, 1);
        $this->assertSame($key->id, $result->id);

        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/api/clients/777/revoke'));
    }

    public function test_change_location_to_unknown_throws_and_keeps_old_key(): void
    {
        [$user, $sub] = $this->activeUserWithSubscription();
        $this->fakePanel();
        $key = VpnKey::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'panel_server_id' => 1,
            'panel_client_id' => 777,
            'name' => 'k',
            'status' => VpnKey::STATUS_ACTIVE,
            'config_text' => '[Interface] orig',
        ]);

        try {
            $this->service->changeLocation($key, 9999);
            $this->fail('expected exception');
        } catch (UnknownLocationException $e) {
            // ok
        }

        $key->refresh();
        $this->assertSame(1, $key->panel_server_id);
        $this->assertSame(777, $key->panel_client_id);
        $this->assertSame(VpnKey::STATUS_ACTIVE, $key->status);
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/revoke'));
    }

    public function test_revoke_marks_local_revoked_and_calls_panel(): void
    {
        [$user, $sub] = $this->activeUserWithSubscription();
        $this->fakePanel();
        $key = VpnKey::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'panel_server_id' => 1,
            'panel_client_id' => 777,
            'name' => 'k',
            'status' => VpnKey::STATUS_ACTIVE,
        ]);

        $this->service->revoke($key);

        $key->refresh();
        $this->assertSame(VpnKey::STATUS_REVOKED, $key->status);
        $this->assertNotNull($key->revoked_at);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/clients/777/revoke'));
    }

    public function test_restore_calls_panel_and_attaches_to_subscription(): void
    {
        [$user, $sub] = $this->activeUserWithSubscription();
        $this->fakePanel();
        $oldSub = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_EXPIRED,
            'months' => 1,
            'price_kopecks' => 20000,
        ]);
        $key = VpnKey::create([
            'user_id' => $user->id,
            'subscription_id' => $oldSub->id,
            'panel_server_id' => 1,
            'panel_client_id' => 777,
            'name' => 'k',
            'status' => VpnKey::STATUS_REVOKED,
            'revoked_at' => now()->subDay(),
        ]);

        $restored = $this->service->restore($key, $sub);

        $restored->refresh();
        $this->assertSame(VpnKey::STATUS_ACTIVE, $restored->status);
        $this->assertNull($restored->revoked_at);
        $this->assertSame($sub->id, $restored->subscription_id);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/clients/777/restore'));
    }
}
