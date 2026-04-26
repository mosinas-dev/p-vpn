<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Models\VpnKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class KeyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['panel.base_url' => 'https://panel.test', 'panel.jwt_token' => 'jwt']);
        Mail::fake();
        Cache::flush();
    }

    private function userWithActiveSubscription(): array
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

    private function fakePanel(array $overrides = []): void
    {
        Http::fake(array_merge([
            'panel.test/api/servers' => Http::response([
                ['id' => 1, 'name' => 'Нидерланды', 'status' => 'active', 'clients_count' => 3],
                ['id' => 2, 'name' => 'Германия', 'status' => 'active', 'clients_count' => 7],
            ], 200),
            'panel.test/api/clients/create' => Http::response([
                'id' => 555, 'config' => '[Interface] new', 'qr_code' => 'data:image/png;base64,Q==',
            ], 200),
            'panel.test/api/clients/*/revoke' => Http::response(['ok' => true], 200),
        ], $overrides));
        Cache::flush();
    }

    public function test_index_renders_with_locations_for_user_without_key(): void
    {
        [$user] = $this->userWithActiveSubscription();
        $this->fakePanel();

        $response = $this->actingAs($user)->get('/keys');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Keys/Index')
            ->where('has_active_subscription', true)
            ->where('key', null)
            ->has('locations', 2)
        );
    }

    public function test_store_creates_key_in_chosen_location(): void
    {
        [$user, $sub] = $this->userWithActiveSubscription();
        $this->fakePanel();

        $response = $this->actingAs($user)->post('/keys', ['server_id' => 1]);

        $response->assertRedirect('/keys');
        $key = VpnKey::where('user_id', $user->id)->first();
        $this->assertNotNull($key);
        $this->assertSame(1, $key->panel_server_id);
        $this->assertSame(555, $key->panel_client_id);
        $this->assertSame($sub->id, $key->subscription_id);
    }

    public function test_store_rejects_when_subscription_not_active(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->fakePanel();

        $response = $this->actingAs($user)->post('/keys', ['server_id' => 1]);

        $response->assertSessionHasErrors('server_id');
        $this->assertSame(0, VpnKey::count());
    }

    public function test_store_rejects_unknown_location(): void
    {
        [$user] = $this->userWithActiveSubscription();
        $this->fakePanel();

        $response = $this->actingAs($user)->post('/keys', ['server_id' => 999]);

        $response->assertSessionHasErrors('server_id');
        $this->assertSame(0, VpnKey::count());
    }

    public function test_store_rejects_second_key_for_same_subscription(): void
    {
        [$user, $sub] = $this->userWithActiveSubscription();
        $this->fakePanel();
        VpnKey::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'panel_server_id' => 1,
            'panel_client_id' => 100,
            'name' => 'first',
            'status' => VpnKey::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($user)->post('/keys', ['server_id' => 2]);

        $response->assertSessionHasErrors('server_id');
        $this->assertSame(1, VpnKey::count());
    }

    public function test_change_location_replaces_key(): void
    {
        [$user, $sub] = $this->userWithActiveSubscription();
        $this->fakePanel([
            'panel.test/api/clients/create' => Http::response([
                'id' => 999, 'config' => '[Interface] de', 'qr_code' => 'data:image/png;base64,X==',
            ], 200),
        ]);
        $key = VpnKey::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'panel_server_id' => 1,
            'panel_client_id' => 100,
            'name' => 'old',
            'status' => VpnKey::STATUS_ACTIVE,
            'config_text' => '[Interface] nl',
        ]);

        $response = $this->actingAs($user)->post("/keys/{$key->id}/change-location", ['server_id' => 2]);

        $response->assertRedirect('/keys');
        $key->refresh();
        $this->assertSame(2, $key->panel_server_id);
        $this->assertSame(999, $key->panel_client_id);
        $this->assertStringContainsString('de', $key->config_text);
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/clients/100/revoke'));
    }

    public function test_change_location_forbidden_for_other_user_key(): void
    {
        [$user, $sub] = $this->userWithActiveSubscription();
        $other = User::factory()->create(['email_verified_at' => now()]);
        $this->fakePanel();
        $key = VpnKey::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'panel_server_id' => 1,
            'panel_client_id' => 100,
            'name' => 'k',
            'status' => VpnKey::STATUS_ACTIVE,
        ]);

        $this->actingAs($other)
            ->post("/keys/{$key->id}/change-location", ['server_id' => 2])
            ->assertForbidden();
    }

    public function test_download_returns_config_file(): void
    {
        [$user, $sub] = $this->userWithActiveSubscription();
        $key = VpnKey::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'panel_server_id' => 1,
            'panel_client_id' => 100,
            'name' => 'k',
            'status' => VpnKey::STATUS_ACTIVE,
            'config_text' => "[Interface]\nPrivateKey = abc\n",
        ]);

        $response = $this->actingAs($user)->get("/keys/{$key->id}/download");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/octet-stream');
        $this->assertStringContainsString('amnezia-', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('PrivateKey = abc', $response->getContent());
    }

    public function test_qr_returns_png_image(): void
    {
        [$user, $sub] = $this->userWithActiveSubscription();
        $pngBase64 = base64_encode("\x89PNG\r\n\x1a\n" . str_repeat('0', 16));
        $key = VpnKey::create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'panel_server_id' => 1,
            'panel_client_id' => 100,
            'name' => 'k',
            'status' => VpnKey::STATUS_ACTIVE,
            'qr_code_base64' => 'data:image/png;base64,' . $pngBase64,
        ]);

        $response = $this->actingAs($user)->get("/keys/{$key->id}/qr");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $this->assertStringStartsWith("\x89PNG", $response->getContent());
    }
}
