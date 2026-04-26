<?php

namespace App\Services\Panel\DTO;

class ClientCreated
{
    public function __construct(
        public readonly int $id,
        public readonly string $config,
        public readonly string $qrCodeBase64,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            config: (string) ($data['config'] ?? ''),
            qrCodeBase64: (string) ($data['qr_code'] ?? ''),
        );
    }
}
