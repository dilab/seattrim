<?php

namespace App\Zoom\Data;

/** Result of a write call: only the tracking id matters (204 bodies are empty). */
final readonly class ApiResult
{
    public function __construct(public ?string $trackingId, public int $httpStatus = 204) {}
}
