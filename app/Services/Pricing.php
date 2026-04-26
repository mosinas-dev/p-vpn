<?php

namespace App\Services;

use InvalidArgumentException;

class Pricing
{
    /** @var array<int,int> months => price in kopecks */
    public const PRICES = [
        1 => 20000,
        3 => 57000,
        6 => 108000,
    ];

    public static function priceFor(int $months): int
    {
        if (!isset(self::PRICES[$months])) {
            throw new InvalidArgumentException("Unknown subscription period: {$months} months");
        }

        return self::PRICES[$months];
    }

    /** @return array<int,int> */
    public static function all(): array
    {
        return self::PRICES;
    }

    public static function isValidPeriod(int $months): bool
    {
        return isset(self::PRICES[$months]);
    }
}
