<?php

namespace App\Services\Panel\DTO;

class ServerInfo
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $status,
        public readonly int $clientsCount,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            status: (string) ($data['status'] ?? 'unknown'),
            clientsCount: (int) ($data['clients_count'] ?? 0),
        );
    }
}
