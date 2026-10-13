<?php

namespace App\Zoom\Data;

/**
 * One license field for PATCH /users/{userId}. Zoom rejects requests that carry
 * more than one of `type`, `zoom_one_type` and `plan_united_type` at the same
 * time (error 300), so a change can only ever hold one of them.
 * See docs/zoom-api-notes.md §4.
 */
final readonly class LicenseChange
{
    public const FIELD_TYPE = 'type';

    public const FIELD_ZOOM_ONE = 'zoom_one_type';

    public const FIELD_UNITED = 'plan_united_type';

    public const UNITED_OFF = 'none';

    private function __construct(public string $field, public int|string $value) {}

    public static function type(int $type): self
    {
        return new self(self::FIELD_TYPE, $type);
    }

    /** 0 turns the Zoom Workplace / Zoom One bundle off. */
    public static function zoomOne(int $option): self
    {
        return new self(self::FIELD_ZOOM_ONE, $option);
    }

    /** "none" turns the Zoom United bundle off. */
    public static function united(string $option): self
    {
        return new self(self::FIELD_UNITED, $option);
    }

    /**
     * Rebuild the change that re-applies a bundle option recorded by ZoomUser::bundleOption().
     *
     * @param  array<string, mixed>  $option
     */
    public static function fromBundleOption(array $option): ?self
    {
        if (isset($option[self::FIELD_ZOOM_ONE]) && (int) $option[self::FIELD_ZOOM_ONE] > 0) {
            return self::zoomOne((int) $option[self::FIELD_ZOOM_ONE]);
        }

        if (isset($option[self::FIELD_UNITED]) && (string) $option[self::FIELD_UNITED] !== '' && $option[self::FIELD_UNITED] !== self::UNITED_OFF) {
            return self::united((string) $option[self::FIELD_UNITED]);
        }

        return null;
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [$this->field => $this->value];
    }

    public function isType(int $type): bool
    {
        return $this->field === self::FIELD_TYPE && $this->value === $type;
    }

    /** Short form for audit reasons and logs, e.g. "type=1" or "zoom_one_type=0". */
    public function describe(): string
    {
        return $this->field.'='.$this->value;
    }
}
