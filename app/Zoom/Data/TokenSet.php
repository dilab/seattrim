<?php

namespace App\Zoom\Data;

final readonly class TokenSet
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
        public string $scope,
        public array $raw = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: (string) $data['access_token'],
            refreshToken: (string) ($data['refresh_token'] ?? ''),
            expiresIn: (int) ($data['expires_in'] ?? 3600),
            scope: (string) ($data['scope'] ?? ''),
            raw: $data,
        );
    }

    /** @return array<int, string> */
    public function scopes(): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim($this->scope)) ?: []));
    }
}
