<?php

namespace App\Zoom\Data;

/** The `feature` object of GET /users/{userId}/settings (docs/zoom-api-notes.md §4). */
final readonly class UserFeatures
{
    /**
     * @param  array<int, string>  $enabledAddOns  Every boolean flag that is true, e.g. ["zoom_phone", "webinar"]
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $zoomPhone,
        public bool $webinar,
        public bool $largeMeeting,
        public bool $zoomEvents,
        public ?int $meetingCapacity,
        public array $enabledAddOns,
        public array $raw,
    ) {}

    /** @param array<string, mixed> $feature */
    public static function fromArray(array $feature): self
    {
        $enabled = [];
        foreach ($feature as $key => $value) {
            if ($value === true && str_starts_with((string) $key, 'zoom_') || in_array($key, ['webinar', 'large_meeting'], true) && $value === true) {
                $enabled[] = (string) $key;
            }
        }

        return new self(
            zoomPhone: (bool) ($feature['zoom_phone'] ?? false),
            webinar: (bool) ($feature['webinar'] ?? false),
            largeMeeting: (bool) ($feature['large_meeting'] ?? false),
            zoomEvents: (bool) ($feature['zoom_events'] ?? false) || (bool) ($feature['zoom_events_unlimited'] ?? false),
            meetingCapacity: isset($feature['meeting_capacity']) ? (int) $feature['meeting_capacity'] : null,
            enabledAddOns: $enabled,
            raw: $feature,
        );
    }

    public function hasAnyAddOn(): bool
    {
        return $this->enabledAddOns !== [];
    }
}
