<?php

namespace App\Services\Cardlink\DTO;

class BillResponse
{
    public function __construct(
        public readonly string $billId,
        public readonly string $payUrl,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            billId: (string) ($data['bill_id'] ?? $data['id'] ?? ''),
            payUrl: (string) ($data['link'] ?? $data['pay_url'] ?? $data['url'] ?? ''),
        );
    }
}
