<?php

namespace App\Jobs;

use App\Models\VpnKey;
use App\Services\Keys\KeyProvisioningService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RevokeVpnKeyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public VpnKey $key)
    {
    }

    public function handle(KeyProvisioningService $service): void
    {
        $this->key->refresh();
        if ($this->key->status === VpnKey::STATUS_REVOKED) {
            return;
        }

        $service->revoke($this->key);
    }
}
