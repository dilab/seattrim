<?php

namespace App\Zoom\Data;

use Carbon\CarbonImmutable;

/**
 * One row of GET /users or the body of GET /users/{userId}. Field names follow
 * docs/zoom-api-notes.md §3. `id` is null for pending users (Zoom omits it).
 */
final readonly class ZoomUser
{
    public const TYPE_BASIC = 1;

    public const TYPE_LICENSED = 2;

    public const TYPE_UNASSIGNED = 4;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_PENDING = 'pending';

    /**
     * @param  array<int, string>  $groupIds
     * @param  array<int, array<string, mixed>>  $licenseInfoList
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?string $id,
        public string $email,
        public string $displayName,
        public int $type,
        public string $status,
        public ?string $dept,
        public array $groupIds,
        public ?string $roleId,
        public ?string $roleName,
        public ?CarbonImmutable $userCreatedAt,
        public ?CarbonImmutable $lastLoginAt,
        public ?string $planUnitedType,
        public ?int $zoomOneType,
        public array $licenseInfoList,
        public array $raw,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data, ?string $status = null): self
    {
        $first = trim((string) ($data['first_name'] ?? ''));
        $last = trim((string) ($data['last_name'] ?? ''));
        $display = trim((string) ($data['display_name'] ?? ''));

        return new self(
            id: isset($data['id']) ? (string) $data['id'] : null,
            email: (string) ($data['email'] ?? ''),
            displayName: $display !== '' ? $display : trim("{$first} {$last}"),
            type: (int) ($data['type'] ?? 0),
            status: (string) ($data['status'] ?? $status ?? self::STATUS_ACTIVE),
            dept: isset($data['dept']) && $data['dept'] !== '' ? (string) $data['dept'] : null,
            groupIds: array_values(array_map('strval', (array) ($data['group_ids'] ?? []))),
            roleId: isset($data['role_id']) ? (string) $data['role_id'] : null,
            roleName: isset($data['role_name']) ? (string) $data['role_name'] : null,
            userCreatedAt: self::date($data['user_created_at'] ?? $data['created_at'] ?? null),
            lastLoginAt: self::date($data['last_login_time'] ?? null),
            planUnitedType: isset($data['plan_united_type']) && $data['plan_united_type'] !== '' ? (string) $data['plan_united_type'] : null,
            zoomOneType: isset($data['zoom_one_type']) ? (int) $data['zoom_one_type'] : null,
            licenseInfoList: array_values((array) ($data['license_info_list'] ?? [])),
            raw: $data,
        );
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Stable key even for pending users, who have no id. */
    public function key(): string
    {
        return $this->id ?? 'email:'.mb_strtolower($this->email);
    }

    public function isLicensed(): bool
    {
        return $this->type === self::TYPE_LICENSED;
    }

    public function isBasic(): bool
    {
        return $this->type === self::TYPE_BASIC;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isDeactivated(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    /** True when any bundle indicator is set (Workplace / Zoom One / Zoom United). */
    public function hasBundle(): bool
    {
        if ($this->zoomOneType !== null && $this->zoomOneType > 0) {
            return true;
        }

        if ($this->planUnitedType !== null) {
            return true;
        }

        foreach ($this->licenseInfoList as $license) {
            if (($license['license_type'] ?? null) === 'ZOOM_WORKPLACE_BUNDLE') {
                return true;
            }
        }

        return false;
    }

    /** Whether the payload carried any bundle field at all (single-user responses do; list rows may not). */
    public function hasBundleFields(): bool
    {
        return array_key_exists('zoom_one_type', $this->raw)
            || array_key_exists('plan_united_type', $this->raw)
            || array_key_exists('license_info_list', $this->raw);
    }

    /** Zoom's built-in Owner role has id "0"; Admin is "1". Custom roles get other ids. */
    public function isOwnerOrAdmin(): bool
    {
        if (in_array($this->roleId, ['0', '1'], true)) {
            return true;
        }

        return $this->roleName !== null && in_array(mb_strtolower($this->roleName), ['owner', 'admin'], true);
    }
}
