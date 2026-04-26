<?php

namespace App\Services\Billing;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Cardlink\CardlinkClient;

class TopupBillFactory
{
    public function __construct(private CardlinkClient $cardlink)
    {
    }

    public function forSubscriptionPurchase(User $user, Subscription $subscription, int $amountKopecks): Payment
    {
        $payment = Payment::create([
            'user_id' => $user->id,
            'amount_kopecks' => $amountKopecks,
            'status' => Payment::STATUS_PENDING,
            'intent' => Payment::INTENT_SUBSCRIPTION_PURCHASE,
            'intent_subscription_id' => $subscription->id,
        ]);

        $bill = $this->cardlink->createBill(
            $amountKopecks,
            (string) $payment->id,
            "Оплата подписки на {$subscription->months} мес ({$user->email})"
        );

        $payment->update([
            'cardlink_bill_id' => $bill->billId,
            'pay_url' => $bill->payUrl,
        ]);

        return $payment->fresh();
    }
}
