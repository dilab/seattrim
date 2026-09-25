<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit log. Rows are never updated except for status transitions and never deleted
 * (except by the full Zoom-data purge, which keeps an anonymised count).
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $zoom_member_id
 * @property string|null $member_email
 * @property string|null $member_name
 * @property string $action
 * @property int|null $from_type
 * @property int|null $to_type
 * @property string $source
 * @property int|null $performed_by
 * @property string $status
 * @property string|null $reason
 * @property string|null $zoom_tracking_id
 * @property string|null $batch_id
 * @property bool $dry_run
 * @property Carbon|null $performed_at
 * @property-read ZoomMember|null $member
 * @property-read User|null $performer
 */
#[Fillable(['organization_id', 'zoom_member_id', 'member_email', 'member_name', 'action', 'from_type', 'to_type', 'source', 'performed_by', 'status', 'reason', 'zoom_tracking_id', 'batch_id', 'dry_run', 'performed_at'])]
class LicenseAction extends Model
{
    use BelongsToOrganization;

    public const ACTION_DOWNGRADE = 'downgrade';

    public const ACTION_RESTORE = 'restore';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_BULK = 'bulk';

    public const SOURCE_RULE = 'rule';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['dry_run' => 'boolean', 'performed_at' => 'datetime', 'from_type' => 'integer', 'to_type' => 'integer'];
    }

    /** @return BelongsTo<ZoomMember, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(ZoomMember::class, 'zoom_member_id');
    }

    /** @return BelongsTo<User, $this> */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function markDone(?string $trackingId = null): void
    {
        $this->forceFill(['status' => self::STATUS_DONE, 'zoom_tracking_id' => $trackingId, 'performed_at' => now()])->save();
    }

    public function markSkipped(string $reason): void
    {
        $this->forceFill(['status' => self::STATUS_SKIPPED, 'reason' => mb_substr($reason, 0, 2000), 'performed_at' => now()])->save();
    }

    public function markFailed(string $reason, ?string $trackingId = null): void
    {
        $this->forceFill(['status' => self::STATUS_FAILED, 'reason' => mb_substr($reason, 0, 2000), 'zoom_tracking_id' => $trackingId, 'performed_at' => now()])->save();
    }
}
