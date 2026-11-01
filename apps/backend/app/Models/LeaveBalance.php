<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveBalance extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'user_id',
        'leave_type_id',
        'period_year',
        'allocated_days',
        'reserved_days',
        'used_days',
        'remaining_days',
    ];

    protected $casts = [
        'period_year' => 'integer',
        'allocated_days' => 'integer',
        'reserved_days' => 'integer',
        'used_days' => 'integer',
        'remaining_days' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(LeaveBalanceLedger::class)->orderByDesc('id');
    }

    public function recalculateRemaining(): int
    {
        $remaining = max(0, (int) $this->allocated_days - (int) $this->reserved_days - (int) $this->used_days);
        $this->remaining_days = $remaining;

        return $remaining;
    }
}
