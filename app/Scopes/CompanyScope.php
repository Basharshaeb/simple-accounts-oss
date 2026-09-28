<?php

namespace App\Scopes;

use App\Services\CompanyContextService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CompanyScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = CompanyContextService::getCompanyId();

        if ($companyId !== null) {
            $builder->where($model->getTable().'.company_id', $companyId);
        }
    }
}
