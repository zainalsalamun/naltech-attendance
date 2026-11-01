<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    protected $casts = ['is_active' => 'boolean', 'deducts_balance' => 'boolean', 'requires_attachment' => 'boolean', 'version' => 'integer', 'annual_quota_days' => 'integer'];

    public function policy(): array
    {
        return $this->only(['id', 'code', 'name', 'version', 'deducts_balance', 'annual_quota_days', 'requires_attachment', 'requirements']);
    }

    public function balances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }
}
