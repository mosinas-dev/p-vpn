<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Cardlink\CardlinkClient;
use App\Services\Pricing;
use App\Services\Subscriptions\SubscriptionManager;
use App\Services\Wallet\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    public function __construct(
        private WalletService $wallet,
        private SubscriptionManager $manager,
        private CardlinkClient $cardlink,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'months' => ['required', 'integer', Rule::in(array_keys(Pricing::all()))],
        ]);

        $user = $request->user();
        $months = (int) $data['months'];
        $price = Pricing::priceFor($months);

        $sub = DB::transaction(function () use ($user, $months, $price) {
            return Subscription::create([
                'user_id' => $user->id,
                'status' => Subscription::STATUS_PENDING,
                'months' => $months,
                'price_kopecks' => $price,
            ]);
        });

        if ($this->wallet->sufficientForRenewal($user, $months)) {
            $this->manager->activate($sub);
            return redirect('/dashboard')->with('success', 'Подписка активирована.');
        }

        $shortfall = $this->wallet->shortfall($user, $months);
        $minTopup = (int) config('wallet.min_topup_kopecks');
        $topupAmount = max($shortfall, $minTopup);

        $payment = Payment::create([
            'user_id' => $user->id,
            'amount_kopecks' => $topupAmount,
            'status' => Payment::STATUS_PENDING,
            'intent' => Payment::INTENT_SUBSCRIPTION_PURCHASE,
            'intent_subscription_id' => $sub->id,
        ]);

        $bill = $this->cardlink->createBill(
            $topupAmount,
            (string) $payment->id,
            "Оплата подписки на {$months} мес ({$user->email})"
        );

        $payment->update([
            'cardlink_bill_id' => $bill->billId,
            'pay_url' => $bill->payUrl,
        ]);

        return redirect()->away($bill->payUrl);
    }
}
