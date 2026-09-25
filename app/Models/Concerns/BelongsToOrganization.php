<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Tenancy\NoCurrentOrganization;
use App\Tenancy\OrganizationScope;
use App\Tenancy\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin Model
 *
 * @property int $organization_id
 * @property-read Organization $organization
 */
trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope(new OrganizationScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('organization_id') === null) {
                $tenancy = app(Tenancy::class);

                if (! $tenancy->has()) {
                    throw new NoCurrentOrganization;
                }

                $model->setAttribute('organization_id', $tenancy->currentOrFail()->getKey());
            }
        });
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Query across all organizations. Only for console commands and tests.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAllOrganizations(Builder $query): Builder
    {
        return $query->withoutGlobalScope(OrganizationScope::class);
    }
}
