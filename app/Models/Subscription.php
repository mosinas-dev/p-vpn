<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id',
        'status',
        'months',
        'price_kopecks',
        'starts_at',
        'ends_at',
        'paid_via_transaction_id',
        'auto_renewed_from_id',
    ];

    protected $casts = [
        'months' => 'integer',
        'price_kopecks' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paidViaTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'paid_via_transaction_id');
    }

    public function vpnKeys(): HasMany
    {
        return $this->hasMany(VpnKey::class);
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class);
    }
}
