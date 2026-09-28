<?php

namespace App\Traits;

use App\Models\Company;
use App\Scopes\CompanyScope;
use App\Services\CompanyContextService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            if (! $model->company_id && CompanyContextService::getCompanyId()) {
                $model->company_id = CompanyContextService::getCompanyId();
            }
        });
    }

    /**
     * Relationship to Company.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
