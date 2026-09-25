<?php

namespace App\Zoom\Data;

/** One user in GET /report/users (docs/zoom-api-notes.md §5). */
final readonly class HostReportRow
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $id,
        public string $email,
        public string $userName,
        public ?string $dept,
        public int $type,
        public int $meetings,
        public int $meetingMinutes,
        public int $participants,
        public array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            email: (string) ($data['email'] ?? ''),
            userName: (string) ($data['user_name'] ?? ''),
            dept: isset($data['dept']) && $data['dept'] !== '' ? (string) $data['dept'] : null,
            type: (int) ($data['type'] ?? 0),
            meetings: (int) ($data['meetings'] ?? 0),
            meetingMinutes: (int) ($data['meeting_minutes'] ?? 0),
            participants: (int) ($data['participants'] ?? 0),
            raw: $data,
        );
    }
}
