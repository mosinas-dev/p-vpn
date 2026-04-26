<?php

namespace App\Services\Subscriptions;

use App\Jobs\RestoreVpnKeyJob;
use App\Models\Subscription;
use App\Models\VpnKey;
use App\Models\WalletTransaction;
use App\Services\Pricing;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;

class SubscriptionManager
{
    public function __construct(private WalletService $wallet)
    {
    }

    public function activate(Subscription $subscription): void
    {
        DB::transaction(function () use ($subscription) {
            $user = $subscription->user;

            $tx = $this->wallet->debit(
                $user->wallet,
                $subscription->price_kopecks,
                WalletTransaction::TYPE_SUBSCRIPTION_DEBIT,
                ['related_subscription_id' => $subscription->id]
            );

            $start = now();
            $previous = $user->lastActiveOrExpired();
            if ($previous && $previous->ends_at && $previous->ends_at->isFuture()) {
                $start = $previous->ends_at;
            }

            $subscription->forceFill([
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => $start,
                'ends_at' => $start->copy()->addMonths($subscription->months),
                'paid_via_transaction_id' => $tx->id,
            ])->save();
        });

        // Если есть revoked-ключ в grace — восстанавливаем (та же локация, без участия юзера).
        // Если ключа нет — НЕ создаём автоматически: юзер сам выберет локацию через /keys.
        $key = $this->findRestorableKey($subscription);
        if ($key) {
            RestoreVpnKeyJob::dispatch($key, $subscription);
        }
    }

    public function autoRenewIfPossible(Subscription $expiring): ?Subscription
    {
        $user = $expiring->user;
        if (!$user->wallet->auto_renew) {
            return null;
        }
        if (!$this->wallet->sufficientForRenewal($user, $expiring->months)) {
            return null;
        }

        $new = Subscription::create([
            'user_id' => $user->id,
            'status' => Subscription::STATUS_PENDING,
            'months' => $expiring->months,
            'price_kopecks' => Pricing::priceFor($expiring->months),
            'auto_renewed_from_id' => $expiring->id,
        ]);

        $this->activate($new);

        return $new->fresh();
    }

    private function findRestorableKey(Subscription $subscription): ?VpnKey
    {
        return $subscription->user->vpnKeys()
            ->where('status', VpnKey::STATUS_REVOKED)
            ->where('revoked_at', '>', now()->subDays(3))
            ->orderByDesc('revoked_at')
            ->first();
    }
}
