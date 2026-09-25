<?php

namespace App\Scan;

use App\Enums\Bucket;

final readonly class Classification
{
    /** @param array<int, string> $protectedReasons */
    public function __construct(
        public Bucket $bucket,
        public array $protectedReasons,
        public string $lastHostedWindow,
        public bool $idle,
        public bool $eligibleForDowngrade,
    ) {}
}
