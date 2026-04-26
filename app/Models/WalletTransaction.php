<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\WalletTransactionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const TYPE_TOPUP = 'topup';
    public const TYPE_SUBSCRIPTION_DEBIT = 'subscription_debit';
    public const TYPE_REFUND = 'refund';
    public const TYPE_BONUS = 'bonus';
    public const TYPE_MANUAL_CREDIT = 'manual_credit';
    public const TYPE_MANUAL_DEBIT = 'manual_debit';

    protected $fillable = [
        'wallet_id',
        'user_id',
        'type',
        'amount_kopecks',
        'balance_after_kopecks',
        'related_payment_id',
        'related_subscription_id',
        'description',
        'created_by_admin_id',
    ];

    protected $casts = [
        'amount_kopecks' => 'integer',
        'balance_after_kopecks' => 'integer',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'related_payment_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'related_subscription_id');
    }
}
