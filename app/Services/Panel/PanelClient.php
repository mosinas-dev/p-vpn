<?php

namespace App\Services\Panel;

use App\Services\Panel\DTO\ClientCreated;
use App\Services\Panel\DTO\ServerInfo;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PanelClient
{
    public function createClient(int $serverId, string $name): ClientCreated
    {
        $response = $this->http()->post('/api/clients/create', [
            'server_id' => $serverId,
            'name' => $name,
        ]);
        $this->ensureOk($response, 'clients/create');

        return ClientCreated::fromArray($response->json() ?? []);
    }

    public function revokeClient(int $clientId): void
    {
        $response = $this->http()->post("/api/clients/{$clientId}/revoke");
        $this->ensureOk($response, "clients/{$clientId}/revoke");
    }

    public function restoreClient(int $clientId): void
    {
        $response = $this->http()->post("/api/clients/{$clientId}/restore");
        $this->ensureOk($response, "clients/{$clientId}/restore");
    }

    /** @return ServerInfo[] */
    public function getServers(): array
    {
        $response = $this->http()->get('/api/servers');
        $this->ensureOk($response, 'servers');

        $data = $response->json();
        $items = is_array($data) ? $data : ($data['servers'] ?? []);

        return array_map(fn (array $item) => ServerInfo::fromArray($item), $items);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('panel.base_url'), '/'))
            ->withToken((string) config('panel.jwt_token'))
            ->acceptJson()
            ->timeout((int) config('panel.http_timeout_seconds', 30));
    }

    private function ensureOk($response, string $endpoint): void
    {
        if (!$response->successful()) {
            Log::warning('panel request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);
            throw new RuntimeException("Panel {$endpoint} failed: HTTP {$response->status()}");
        }
    }
}
