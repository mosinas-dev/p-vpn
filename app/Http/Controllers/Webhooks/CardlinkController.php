<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Cardlink\DTO\WebhookPayload;
use App\Services\Cardlink\WebhookVerifier;
use App\Services\Subscriptions\SubscriptionManager;
use App\Services\Wallet\Exceptions\InsufficientFundsException;
use App\Services\Wallet\WalletService;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CardlinkController extends Controller
{
    public function __construct(
        private WebhookVerifier $verifier,
        private WalletService $wallet,
        private SubscriptionManager $subscriptions,
    ) {
    }

    public function handle(Request $request): Response
    {
        if (!$this->verifier->verify($request)) {
            Log::warning('cardlink webhook: bad signature');
            return response('bad signature', 403);
        }

        $payload = WebhookPayload::fromArray($request->json()->all());

        $payment = Payment::where('cardlink_bill_id', $payload->billId)->first();
        if (!$payment) {
            return response('unknown bill', 404);
        }

        return DB::transaction(function () use ($payment, $payload) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status === Payment::STATUS_SUCCESS) {
                return response('ok (already processed)', 200);
            }

            if (!$payload->isSuccess()) {
                $payment->update([
                    'status' => Payment::STATUS_FAIL,
                    'cardlink_payment_id' => $payload->paymentId,
                    'paid_at' => $payload->paidAt,
                    'raw_payload' => $payload->raw,
                ]);
                return response('ok (fail recorded)', 200);
            }

            $payment->update([
                'status' => Payment::STATUS_SUCCESS,
                'cardlink_payment_id' => $payload->paymentId,
                'paid_at' => $payload->paidAt,
                'raw_payload' => $payload->raw,
            ]);

            $user = $payment->user;
            $this->wallet->credit(
                $user->wallet,
                $payment->amount_kopecks,
                WalletTransaction::TYPE_TOPUP,
                ['related_payment_id' => $payment->id]
            );

            if ($payment->intent === Payment::INTENT_SUBSCRIPTION_PURCHASE && $payment->intent_subscription_id) {
                $sub = Subscription::find($payment->intent_subscription_id);
                if ($sub && $sub->status === Subscription::STATUS_PENDING) {
                    try {
                        $this->subscriptions->activate($sub);
                    } catch (InsufficientFundsException $e) {
                        Log::warning('cardlink webhook: race on subscription activation', [
                            'subscription_id' => $sub->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            }

            return response('ok', 200);
        });
    }
}
