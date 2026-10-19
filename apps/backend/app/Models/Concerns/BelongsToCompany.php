<?php

namespace App\Models\Concerns;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            if ($id = app(CompanyContext::class)->id) {
                $query->where($query->getModel()->qualifyColumn('company_id'), $id);
            }
        });

        static::creating(function ($model) {
            if ($id = app(CompanyContext::class)->id) {
                $model->company_id = $id;
            }
        });
    }
}
