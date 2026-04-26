<?php

namespace App\Services\Keys;

use App\Mail\KeyIssued;
use App\Models\Subscription;
use App\Models\VpnKey;
use App\Services\Keys\Exceptions\KeyAlreadyIssuedException;
use App\Services\Keys\Exceptions\NoActiveSubscriptionException;
use App\Services\Keys\Exceptions\UnknownLocationException;
use App\Services\Panel\PanelClient;
use App\Services\Panel\ServerCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class KeyProvisioningService
{
    public function __construct(
        private PanelClient $panel,
        private ServerCatalog $catalog,
    ) {
    }

    public function issue(Subscription $subscription, int $serverId): VpnKey
    {
        if ($subscription->status !== Subscription::STATUS_ACTIVE) {
            throw new NoActiveSubscriptionException("Subscription {$subscription->id} is not active");
        }

        $existing = VpnKey::where('subscription_id', $subscription->id)
            ->where('status', VpnKey::STATUS_ACTIVE)
            ->exists();
        if ($existing) {
            throw new KeyAlreadyIssuedException(
                "Subscription {$subscription->id} already has an active key — change location instead"
            );
        }

        $server = $this->catalog->find($serverId)
            ?? throw new UnknownLocationException("Location {$serverId} is not available");

        $created = $this->panel->createClient($server->id, $this->makeClientName($subscription));

        $key = DB::transaction(fn () => VpnKey::create([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'panel_server_id' => $server->id,
            'panel_client_id' => $created->id,
            'name' => $this->makeClientName($subscription),
            'status' => VpnKey::STATUS_ACTIVE,
            'config_text' => $created->config,
            'qr_code_base64' => $created->qrCodeBase64,
        ]));

        Mail::to($subscription->user)->queue(new KeyIssued($key));
        $this->catalog->flushCache();

        return $key;
    }

    public function changeLocation(VpnKey $key, int $newServerId): VpnKey
    {
        if ($key->panel_server_id === $newServerId) {
            return $key;
        }

        $newServer = $this->catalog->find($newServerId)
            ?? throw new UnknownLocationException("Location {$newServerId} is not available");

        // 1. Создаём нового клиента ПЕРВЫМ — чтобы при сбое старый ещё работал
        $created = $this->panel->createClient($newServer->id, $key->name);

        try {
            // 2. Только теперь revoke-аем старого
            $this->panel->revokeClient($key->panel_client_id);
        } catch (\Throwable $e) {
            Log::warning('changeLocation: failed to revoke old client, leaving in panel for manual cleanup', [
                'old_panel_client_id' => $key->panel_client_id,
                'error' => $e->getMessage(),
            ]);
            // не фейлим: новый клиент уже создан, юзеру отдадим его конфиг
        }

        return DB::transaction(function () use ($key, $newServer, $created) {
            $key->update([
                'panel_server_id' => $newServer->id,
                'panel_client_id' => $created->id,
                'config_text' => $created->config,
                'qr_code_base64' => $created->qrCodeBase64,
                'status' => VpnKey::STATUS_ACTIVE,
                'revoked_at' => null,
            ]);
            $this->catalog->flushCache();
            return $key->fresh();
        });
    }

    public function revoke(VpnKey $key): void
    {
        try {
            $this->panel->revokeClient($key->panel_client_id);
        } catch (\Throwable $e) {
            Log::warning('revoke: panel call failed, marking local revoked anyway', [
                'panel_client_id' => $key->panel_client_id,
                'error' => $e->getMessage(),
            ]);
        }

        $key->update([
            'status' => VpnKey::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);
    }

    public function restore(VpnKey $key, Subscription $subscription): VpnKey
    {
        $this->panel->restoreClient($key->panel_client_id);

        $key->update([
            'status' => VpnKey::STATUS_ACTIVE,
            'revoked_at' => null,
            'subscription_id' => $subscription->id,
        ]);

        return $key->fresh();
    }

    private function makeClientName(Subscription $subscription): string
    {
        return "sub-{$subscription->id}-{$subscription->user->email}";
    }
}
