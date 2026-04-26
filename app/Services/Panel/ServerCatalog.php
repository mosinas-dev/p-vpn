<?php

namespace App\Services\Panel;

use App\Services\Panel\DTO\ServerInfo;
use Illuminate\Support\Facades\Cache;

class ServerCatalog
{
    private const CACHE_KEY = 'panel:servers';
    private const CACHE_TTL_SECONDS = 600; // 10 минут

    public function __construct(private PanelClient $panel)
    {
    }

    /** @return ServerInfo[] */
    public function available(): array
    {
        $all = $this->loadCached();

        return array_values(array_filter($all, fn (ServerInfo $s) => $s->status === 'active'));
    }

    public function find(int $serverId): ?ServerInfo
    {
        foreach ($this->available() as $s) {
            if ($s->id === $serverId) {
                return $s;
            }
        }
        return null;
    }

    public function leastLoaded(): ?ServerInfo
    {
        $active = $this->available();
        if ($active === []) {
            return null;
        }
        usort($active, fn (ServerInfo $a, ServerInfo $b) => $a->clientsCount <=> $b->clientsCount);
        return $active[0];
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return ServerInfo[] */
    private function loadCached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => $this->panel->getServers());
    }
}
