<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
class OrganizationScope implements Scope
{
    /**
     * When a tenant is set, every query on a tenant model is constrained to it.
     * When no tenant is set (console, tests that build data directly), the
     * query is left alone; creating still fails without a tenant (see trait).
     */
    public function apply(Builder $builder, Model $model): void
    {
        $tenancy = app(Tenancy::class);

        if ($tenancy->has()) {
            $builder->where($model->qualifyColumn('organization_id'), $tenancy->currentOrFail()->getKey());
        }
    }
}
