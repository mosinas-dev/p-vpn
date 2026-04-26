<?php

namespace Tests\Unit;

use App\Services\Panel\PanelClient;
use App\Services\Panel\ServerCatalog;
use App\Services\Panel\DTO\ServerInfo;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class ServerCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_available_returns_only_active_servers(): void
    {
        $panel = Mockery::mock(PanelClient::class);
        $panel->shouldReceive('getServers')->once()->andReturn([
            new ServerInfo(1, 'NL', 'active', 10),
            new ServerInfo(2, 'DE', 'inactive', 0),
            new ServerInfo(3, 'US', 'active', 22),
        ]);
        $catalog = new ServerCatalog($panel);

        $list = $catalog->available();

        $this->assertCount(2, $list);
        $this->assertSame([1, 3], array_map(fn ($s) => $s->id, $list));
    }

    public function test_available_caches_response_between_calls(): void
    {
        $panel = Mockery::mock(PanelClient::class);
        $panel->shouldReceive('getServers')->once()->andReturn([
            new ServerInfo(1, 'NL', 'active', 5),
        ]);
        $catalog = new ServerCatalog($panel);

        $catalog->available();
        $catalog->available(); // не должен дёргать panel второй раз
    }

    public function test_find_returns_active_server_by_id_or_null(): void
    {
        $panel = Mockery::mock(PanelClient::class);
        $panel->shouldReceive('getServers')->andReturn([
            new ServerInfo(1, 'NL', 'active', 0),
            new ServerInfo(99, 'X', 'inactive', 0),
        ]);
        $catalog = new ServerCatalog($panel);

        $this->assertSame('NL', $catalog->find(1)?->name);
        $this->assertNull($catalog->find(404), 'unknown id → null');
        $this->assertNull($catalog->find(99), 'inactive → null');
    }

    public function test_least_loaded_returns_active_with_smallest_clients_count(): void
    {
        $panel = Mockery::mock(PanelClient::class);
        $panel->shouldReceive('getServers')->andReturn([
            new ServerInfo(1, 'NL', 'active', 50),
            new ServerInfo(2, 'DE', 'active', 5),
            new ServerInfo(3, 'US', 'active', 30),
        ]);
        $catalog = new ServerCatalog($panel);

        $this->assertSame(2, $catalog->leastLoaded()?->id);
    }
}
