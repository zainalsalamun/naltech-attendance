<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalanceLedger extends Model
{
    use BelongsToCompany, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'leave_balance_id',
        'user_id',
        'actor_id',
        'permission_id',
        'transaction_type',
        'amount_days',
        'previous_allocated',
        'previous_reserved',
        'previous_used',
        'previous_remaining',
        'current_allocated',
        'current_reserved',
        'current_used',
        'current_remaining',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'amount_days' => 'integer',
        'previous_allocated' => 'integer',
        'previous_reserved' => 'integer',
        'previous_used' => 'integer',
        'previous_remaining' => 'integer',
        'current_allocated' => 'integer',
        'current_reserved' => 'integer',
        'current_used' => 'integer',
        'current_remaining' => 'integer',
        'created_at' => 'datetime',
    ];

    public function leaveBalance(): BelongsTo
    {
        return $this->belongsTo(LeaveBalance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function getTransactionLabelAttribute(): string
    {
        return match ($this->transaction_type) {
            'allocation' => 'Alokasi saldo',
            'adjustment' => 'Penyesuaian kuota',
            'reserve' => 'Cadangan pengajuan',
            'use' => 'Pemakaian cuti',
            'release_reserved' => 'Pengembalian cadangan',
            'restore_used' => 'Pengembalian saldo terpakai',
            default => $this->transaction_type,
        };
    }
}
