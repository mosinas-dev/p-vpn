<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WalletTopupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cardlink.api_url' => 'https://cardlink.test/api/v1',
            'cardlink.secret_key' => 'sk_topup',
            'cardlink.shop_id' => 'shop_topup',
            'wallet.min_topup_rubles' => 100,
        ]);
    }

    public function test_unauthenticated_user_redirected_from_wallet_index(): void
    {
        $this->get('/wallet')->assertRedirect('/login');
    }

    public function test_wallet_index_renders_for_authenticated_verified_user(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->get('/wallet')->assertOk();
    }

    public function test_topup_creates_payment_in_kopecks_from_rubles_input(): void
    {
        Http::fake([
            'cardlink.test/*' => Http::response([
                'bill_id' => 'bill_user_topup',
                'link' => 'https://cardlink.test/pay/abc',
            ], 200),
        ]);
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->post('/wallet/topup', [
            'amount_rubles' => 500,
        ]);

        $response->assertRedirect('https://cardlink.test/pay/abc');
        $payment = Payment::where('user_id', $user->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(50000, $payment->amount_kopecks, '500 ₽ = 50000 коп.');
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(Payment::INTENT_WALLET_TOPUP, $payment->intent);
        $this->assertSame('bill_user_topup', $payment->cardlink_bill_id);
    }

    public function test_topup_rejects_amount_below_min(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->post('/wallet/topup', [
            'amount_rubles' => 50,
        ]);

        $response->assertSessionHasErrors('amount_rubles');
        $this->assertSame(0, Payment::count());
    }

    public function test_topup_rejects_non_integer_rubles(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->post('/wallet/topup', [
            'amount_rubles' => 'абвгд',
        ]);

        $response->assertSessionHasErrors('amount_rubles');
        $this->assertSame(0, Payment::count());
    }

    public function test_toggle_auto_renew_persists_change(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->assertTrue($user->wallet->auto_renew);

        $this->actingAs($user)->post('/wallet/auto-renew', ['auto_renew' => false])
            ->assertRedirect('/wallet');

        $this->assertFalse($user->wallet->fresh()->auto_renew);
    }
}
