<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $scan_id
 * @property string $source
 * @property array<int, string>|null $plan_names
 * @property int|null $purchased_seats
 * @property int|null $used_seats
 * @property int|null $unassigned_seats
 * @property int|null $pending_seats
 * @property array<string, mixed>|null $raw
 */
#[Fillable(['organization_id', 'scan_id', 'source', 'plan_names', 'purchased_seats', 'used_seats', 'unassigned_seats', 'pending_seats', 'raw'])]
class ZoomSeatsSnapshot extends Model
{
    use BelongsToOrganization;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['plan_names' => 'array', 'raw' => 'array'];
    }

    /** @return BelongsTo<Scan, $this> */
    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function purchasedKnown(): bool
    {
        return $this->purchased_seats !== null;
    }
}
