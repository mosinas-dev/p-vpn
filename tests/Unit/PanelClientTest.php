<?php

namespace Tests\Unit;

use App\Services\Panel\PanelClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PanelClientTest extends TestCase
{
    private PanelClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'panel.base_url' => 'https://panel.test',
            'panel.jwt_token' => 'jwt_test_token',
            'panel.http_timeout_seconds' => 5,
        ]);
        $this->client = $this->app->make(PanelClient::class);
    }

    public function test_create_client_posts_to_api_and_returns_dto(): void
    {
        Http::fake([
            'panel.test/api/clients/create' => Http::response([
                'id' => 101,
                'config' => '[Interface]...',
                'qr_code' => 'data:image/png;base64,iVBORw0KGgo=',
            ], 200),
        ]);

        $result = $this->client->createClient(7, 'user@example.com');

        $this->assertSame(101, $result->id);
        $this->assertStringStartsWith('[Interface]', $result->config);
        $this->assertStringStartsWith('data:image/png;base64,', $result->qrCodeBase64);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://panel.test/api/clients/create'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer jwt_test_token')
                && $request['server_id'] === 7
                && $request['name'] === 'user@example.com';
        });
    }

    public function test_revoke_client_posts_to_revoke_endpoint(): void
    {
        Http::fake([
            'panel.test/api/clients/101/revoke' => Http::response(['ok' => true], 200),
        ]);

        $this->client->revokeClient(101);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://panel.test/api/clients/101/revoke'
                && $request->method() === 'POST';
        });
    }

    public function test_restore_client_posts_to_restore_endpoint(): void
    {
        Http::fake([
            'panel.test/api/clients/101/restore' => Http::response(['ok' => true], 200),
        ]);

        $this->client->restoreClient(101);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://panel.test/api/clients/101/restore'
                && $request->method() === 'POST';
        });
    }

    public function test_get_servers_returns_list_of_dto(): void
    {
        Http::fake([
            'panel.test/api/servers' => Http::response([
                ['id' => 1, 'name' => 'srv-1', 'status' => 'active', 'clients_count' => 12],
                ['id' => 2, 'name' => 'srv-2', 'status' => 'active', 'clients_count' => 5],
            ], 200),
        ]);

        $servers = $this->client->getServers();

        $this->assertCount(2, $servers);
        $this->assertSame(1, $servers[0]->id);
        $this->assertSame('srv-1', $servers[0]->name);
        $this->assertSame(5, $servers[1]->clientsCount);
    }

    public function test_throws_on_non_2xx(): void
    {
        Http::fake([
            'panel.test/api/clients/create' => Http::response(['error' => 'fail'], 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->client->createClient(1, 'name');
    }
}
