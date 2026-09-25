<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $trigger
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property array<string, mixed>|null $totals
 * @property array<int, array{code: string, message: string}>|null $warnings
 * @property string|null $error
 * @property int $threshold_days
 * @property-read ZoomSeatsSnapshot|null $snapshot
 */
#[Fillable(['organization_id', 'trigger', 'status', 'started_at', 'finished_at', 'totals', 'warnings', 'error', 'threshold_days'])]
class Scan extends Model
{
    use BelongsToOrganization;

    public const TRIGGER_MANUAL = 'manual';

    public const TRIGGER_SCHEDULED = 'scheduled';

    public const TRIGGER_ONBOARDING = 'onboarding';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'totals' => 'array',
            'warnings' => 'array',
            'threshold_days' => 'integer',
        ];
    }

    /** @return HasOne<ZoomSeatsSnapshot, $this> */
    public function snapshot(): HasOne
    {
        return $this->hasOne(ZoomSeatsSnapshot::class);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    public function addWarning(string $code, string $message): void
    {
        $warnings = $this->warnings ?? [];
        $warnings[] = ['code' => $code, 'message' => $message];
        $this->warnings = $warnings;
    }

    public function total(string $path, mixed $default = null): mixed
    {
        return data_get($this->totals ?? [], $path, $default);
    }
}
