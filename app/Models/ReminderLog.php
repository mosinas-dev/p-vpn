<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    /** @use HasFactory<\Database\Factories\ReminderLogFactory> */
    use HasFactory;

    public $timestamps = false;

    public const KIND_TOPUP_NEEDED_D7 = 'topup_needed_d7';
    public const KIND_TOPUP_NEEDED_D3 = 'topup_needed_d3';
    public const KIND_TOPUP_NEEDED_D1 = 'topup_needed_d1';
    public const KIND_EXPIRED = 'expired';
    public const KIND_GRACE_END = 'grace_end';
    public const KIND_AUTO_RENEWED = 'auto_renewed';

    protected $fillable = [
        'subscription_id',
        'kind',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
