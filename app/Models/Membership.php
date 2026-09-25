<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property Role $role
 * @property-read Organization $organization
 * @property-read User $user
 */
#[Fillable(['organization_id', 'user_id', 'role'])]
class Membership extends Pivot
{
    public $incrementing = true;

    protected $table = 'organization_user';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['role' => Role::class];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
