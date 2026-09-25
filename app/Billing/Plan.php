<?php

namespace App\Billing;

final readonly class Plan
{
    /** @param array<int, string> $features */
    public function __construct(
        public string $key,
        public string $name,
        public int $priceCents,
        public ?int $seatLimit,
        public ?string $stripePrice,
        public array $features,
        public ?int $admins,
    ) {}

    public function isFree(): bool
    {
        return $this->key === 'free';
    }

    public function allows(string $feature): bool
    {
        return in_array($feature, $this->features, true);
    }

    public function fitsSeats(int $seats): bool
    {
        return $this->seatLimit === null || $seats <= $this->seatLimit;
    }
}
