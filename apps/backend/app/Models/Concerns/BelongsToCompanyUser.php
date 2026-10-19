<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompanyUser
{
    protected static function bootBelongsToCompanyUser(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            if (app(CompanyContext::class)->id) {
                $query->whereHas('user');
            }
        });

        static::saving(function ($model) {
            if (app(CompanyContext::class)->id) {
                abort_unless(User::whereKey($model->user_id)->exists(), 403,
                    'Pengguna tidak terdaftar di perusahaan Anda.');
            }
        });
    }
}
