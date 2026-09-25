<?php

namespace App\Zoom\Data;

/** GET /users/summary (docs/zoom-api-notes.md §3). */
final readonly class UserSummary
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public int $licensed,
        public int $basic,
        public int $pending,
        public int $rooms,
        public int $joinOnly,
        public int $onPrem,
        public int $total,
        public array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            licensed: (int) ($data['licensed_users_count'] ?? 0),
            basic: (int) ($data['basic_users_count'] ?? 0),
            pending: (int) ($data['pending_users_count'] ?? 0),
            rooms: (int) ($data['room_users_count'] ?? 0),
            joinOnly: (int) ($data['join_only_users_count'] ?? 0),
            onPrem: (int) ($data['on_prem_users_count'] ?? 0),
            total: (int) ($data['total_users_count'] ?? 0),
            raw: $data,
        );
    }
}
