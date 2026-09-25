<?php

namespace App\Zoom\Exceptions;

use RuntimeException;

/**
 * Any non-success answer from Zoom. Carries Zoom's own error code (body.code),
 * the HTTP status and the tracking id so the audit log can show it.
 */
class ZoomApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly ?int $zoomCode = null,
        public readonly ?string $trackingId = null,
        public readonly ?string $endpoint = null,
        /** @var array<string, mixed> */
        public readonly array $body = [],
    ) {
        parent::__construct($message, $zoomCode ?? $httpStatus);
    }

    public function isRateLimited(): bool
    {
        return $this->httpStatus === 429;
    }

    public function isAuthFailure(): bool
    {
        return $this->httpStatus === 401 || $this->zoomCode === 124 || $this->zoomCode === 4700;
    }

    public function isNotFound(): bool
    {
        return $this->httpStatus === 404 || $this->zoomCode === 1001 || $this->zoomCode === 1120;
    }

    public function isPermissionDenied(): bool
    {
        return $this->httpStatus === 403 || $this->zoomCode === 200 || $this->zoomCode === 1108;
    }

    /** Human readable for audit rows: "Zoom error 2034: …". */
    public function summary(): string
    {
        $prefix = $this->zoomCode !== null ? "Zoom error {$this->zoomCode}" : "HTTP {$this->httpStatus}";

        return "{$prefix}: {$this->getMessage()}";
    }
}
