<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $type
 * @property string $value
 * @property string|null $reason
 * @property int|null $created_by
 * @property Carbon|null $expires_at
 */
#[Fillable(['organization_id', 'type', 'value', 'reason', 'created_by', 'expires_at'])]
class Exclusion extends Model
{
    use BelongsToOrganization;

    public const TYPE_EMAIL = 'email';

    public const TYPE_DOMAIN = 'domain';

    public const TYPE_GROUP = 'group';

    public const TYPES = [self::TYPE_EMAIL, self::TYPE_DOMAIN, self::TYPE_GROUP];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function describe(): string
    {
        $label = match ($this->type) {
            self::TYPE_EMAIL => 'email',
            self::TYPE_DOMAIN => 'domain',
            self::TYPE_GROUP => 'group',
            default => $this->type,
        };

        return trim("{$label} {$this->value}".($this->reason ? " ({$this->reason})" : ''));
    }
}
