<?php

namespace App\Zoom;

/**
 * Verifies Zoom webhook requests (docs/zoom-api-notes.md §11).
 * message = "v0:{x-zm-request-timestamp}:{raw body}", HMAC-SHA256 with the secret token, hex.
 */
class WebhookSignature
{
    public const MAX_SKEW_SECONDS = 300;

    public function __construct(private readonly string $secretToken) {}

    public static function fromConfig(): self
    {
        return new self((string) config('zoom.webhook_secret_token'));
    }

    public function expected(string $timestamp, string $rawBody): string
    {
        return 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$rawBody}", $this->secretToken);
    }

    public function verify(?string $timestamp, ?string $signature, string $rawBody, ?int $now = null): bool
    {
        if ($this->secretToken === '' || $timestamp === null || $signature === null || $timestamp === '' || $signature === '') {
            return false;
        }

        if (! ctype_digit($timestamp)) {
            return false;
        }

        $now ??= time();
        if (abs($now - (int) $timestamp) > self::MAX_SKEW_SECONDS) {
            return false;
        }

        return hash_equals($this->expected($timestamp, $rawBody), $signature);
    }

    /**
     * Response for the endpoint.url_validation challenge.
     *
     * @return array{plainToken: string, encryptedToken: string}
     */
    public function challengeResponse(string $plainToken): array
    {
        return [
            'plainToken' => $plainToken,
            'encryptedToken' => hash_hmac('sha256', $plainToken, $this->secretToken),
        ];
    }
}
