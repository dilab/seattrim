<?php

namespace App\Support;

use App\Models\Organization;
use Illuminate\Support\Number;

class Money
{
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'AUD', 'CAD', 'SGD', 'JPY', 'NZD', 'INR'];

    public static function format(?int $cents, string $currency = 'USD', bool $whole = true): string
    {
        if ($cents === null) {
            return '—';
        }

        $amount = $cents / 100;

        return (string) Number::currency($whole ? round($amount) : $amount, in: $currency, precision: $whole ? 0 : 2);
    }

    public static function forOrganization(Organization $organization, ?int $cents, bool $whole = true): string
    {
        return self::format($cents, (string) $organization->setting('currency', 'USD'), $whole);
    }
}
