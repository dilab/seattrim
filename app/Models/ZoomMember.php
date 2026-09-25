<?php

namespace App\Models;

use App\Enums\Bucket;
use App\Models\Concerns\BelongsToOrganization;
use App\Zoom\Data\ZoomUser;
use Database\Factories\ZoomMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $member_key
 * @property string|null $zoom_user_id
 * @property string $email
 * @property string|null $name
 * @property string $status
 * @property int $type
 * @property string|null $dept
 * @property array<int, string>|null $group_ids
 * @property string|null $role_id
 * @property string|null $role_name
 * @property Carbon|null $created_at_zoom
 * @property Carbon|null $last_login_at
 * @property string|null $last_hosted_window
 * @property array<string, int>|null $meetings_by_window
 * @property int|null $upcoming_meetings_count
 * @property bool $is_room
 * @property bool $has_phone
 * @property bool $has_bundled_license
 * @property bool $bundle_known
 * @property bool $has_webinar_addon
 * @property bool $has_large_meeting_addon
 * @property array<int, string>|null $add_ons
 * @property Bucket $bucket
 * @property array<int, string>|null $protected_reasons
 * @property bool $eligible_for_downgrade
 * @property Carbon|null $excluded_until
 * @property array<string, mixed>|null $raw
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $removed_at
 */
#[Fillable([
    'organization_id', 'member_key', 'zoom_user_id', 'email', 'name', 'status', 'type', 'dept', 'group_ids', 'role_id', 'role_name',
    'created_at_zoom', 'last_login_at', 'last_hosted_window', 'meetings_by_window', 'upcoming_meetings_count', 'is_room', 'has_phone',
    'has_bundled_license', 'bundle_known', 'has_webinar_addon', 'has_large_meeting_addon', 'add_ons', 'bucket', 'protected_reasons',
    'eligible_for_downgrade', 'excluded_until', 'raw', 'last_seen_at', 'removed_at',
])]
class ZoomMember extends Model
{
    use BelongsToOrganization;

    /** @use HasFactory<ZoomMemberFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => 'integer',
            'group_ids' => 'array',
            'created_at_zoom' => 'datetime',
            'last_login_at' => 'datetime',
            'meetings_by_window' => 'array',
            'upcoming_meetings_count' => 'integer',
            'is_room' => 'boolean',
            'has_phone' => 'boolean',
            'has_bundled_license' => 'boolean',
            'bundle_known' => 'boolean',
            'has_webinar_addon' => 'boolean',
            'has_large_meeting_addon' => 'boolean',
            'add_ons' => 'array',
            'bucket' => Bucket::class,
            'protected_reasons' => 'array',
            'eligible_for_downgrade' => 'boolean',
            'excluded_until' => 'datetime',
            'raw' => 'array',
            'last_seen_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /** @return HasMany<LicenseAction, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(LicenseAction::class)->latest('id');
    }

    /** @return HasMany<DowngradeNotice, $this> */
    public function notices(): HasMany
    {
        return $this->hasMany(DowngradeNotice::class)->latest('id');
    }

    /** @param Builder<ZoomMember> $query */
    public function scopePresent(Builder $query): void
    {
        $query->whereNull('removed_at');
    }

    /** @param Builder<ZoomMember> $query */
    public function scopeLicensed(Builder $query): void
    {
        $query->where('type', ZoomUser::TYPE_LICENSED);
    }

    public function isLicensed(): bool
    {
        return $this->type === ZoomUser::TYPE_LICENSED;
    }

    public function isPresent(): bool
    {
        return $this->removed_at === null;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            ZoomUser::TYPE_BASIC => 'Basic',
            ZoomUser::TYPE_LICENSED => 'Licensed',
            ZoomUser::TYPE_UNASSIGNED => 'No meetings license',
            default => "Type {$this->type}",
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            ZoomUser::STATUS_INACTIVE => 'Deactivated',
            ZoomUser::STATUS_PENDING => 'Pending',
            default => 'Active',
        };
    }

    public function isKeptByUser(): bool
    {
        return $this->excluded_until !== null && $this->excluded_until->isFuture();
    }
}
