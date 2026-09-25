<?php

namespace App\Scan;

use App\Models\Exclusion;
use Illuminate\Support\Collection;

/** Matches a member against the org's exclusion list. Expired rules are ignored. */
class ExclusionMatcher
{
    /** @param Collection<int, Exclusion> $exclusions */
    public function __construct(private readonly Collection $exclusions) {}

    /** @param array<int, string> $groupIds */
    public function reasonFor(string $email, array $groupIds): ?string
    {
        $email = mb_strtolower(trim($email));
        $domain = str_contains($email, '@') ? substr($email, strrpos($email, '@') + 1) : '';

        foreach ($this->exclusions as $exclusion) {
            if ($exclusion->isExpired()) {
                continue;
            }

            $value = mb_strtolower(trim($exclusion->value));

            $hit = match ($exclusion->type) {
                Exclusion::TYPE_EMAIL => $value === $email,
                Exclusion::TYPE_DOMAIN => $domain !== '' && ($value === $domain || $value === '@'.$domain || str_ends_with($domain, '.'.ltrim($value, '@'))),
                Exclusion::TYPE_GROUP => in_array($exclusion->value, $groupIds, true),
                default => false,
            };

            if ($hit) {
                return $exclusion->describe();
            }
        }

        return null;
    }
}
