<?php

namespace App\Zoom\Data;

use Carbon\CarbonImmutable;

/** One meeting of GET /users/{userId}/meetings?type=upcoming. Topic and time only. */
final readonly class UpcomingMeeting
{
    /** @param array<string, mixed> $raw */
    public function __construct(
        public string $id,
        public string $topic,
        public ?CarbonImmutable $startTime,
        public int $type,
        public array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $start = null;
        if (! empty($data['start_time'])) {
            try {
                $start = CarbonImmutable::parse((string) $data['start_time']);
            } catch (\Throwable) {
            }
        }

        return new self(
            id: (string) ($data['id'] ?? ''),
            topic: (string) ($data['topic'] ?? ''),
            startTime: $start,
            type: (int) ($data['type'] ?? 0),
            raw: ['id' => $data['id'] ?? null, 'topic' => $data['topic'] ?? null, 'start_time' => $data['start_time'] ?? null, 'type' => $data['type'] ?? null],
        );
    }
}
