<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $zoom_member_id
 * @property Carbon|null $sent_at
 * @property Carbon $scheduled_for
 * @property Carbon|null $kept_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property int|null $executed_action_id
 * @property-read ZoomMember|null $member
 */
#[Fillable(['organization_id', 'zoom_member_id', 'sent_at', 'scheduled_for', 'kept_at', 'cancelled_at', 'cancel_reason', 'executed_action_id'])]
class DowngradeNotice extends Model
{
    use BelongsToOrganization;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'scheduled_for' => 'datetime', 'kept_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return BelongsTo<ZoomMember, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(ZoomMember::class, 'zoom_member_id');
    }

    /** @return BelongsTo<LicenseAction, $this> */
    public function executedAction(): BelongsTo
    {
        return $this->belongsTo(LicenseAction::class, 'executed_action_id');
    }

    /** @param Builder<DowngradeNotice> $query */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('kept_at')->whereNull('cancelled_at')->whereNull('executed_action_id');
    }

    public function isOpen(): bool
    {
        return $this->kept_at === null && $this->cancelled_at === null && $this->executed_action_id === null;
    }

    public function statusLabel(): string
    {
        return match (true) {
            $this->executed_action_id !== null => 'executed',
            $this->kept_at !== null => 'kept',
            $this->cancelled_at !== null => 'cancelled',
            default => 'pending',
        };
    }
}
