<?php

namespace App\Jobs;

use App\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CreateVpnKeyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription)
    {
    }

    public function handle(): void
    {
        // Реализация во второй фазе: PanelClient::createClient + сохранение vpn_keys.
    }
}
