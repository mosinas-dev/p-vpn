<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VpnKey extends Model
{
    /** @use HasFactory<\Database\Factories\VpnKeyFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'user_id',
        'subscription_id',
        'panel_server_id',
        'panel_client_id',
        'name',
        'status',
        'config_text',
        'qr_code_base64',
        'revoked_at',
    ];

    protected $casts = [
        'panel_server_id' => 'integer',
        'panel_client_id' => 'integer',
        'created_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
