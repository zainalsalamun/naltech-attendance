<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use \App\Models\Concerns\BelongsToCompanyUser;
    use \App\Models\Concerns\HasSubmissionApproval;
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function ($row) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('permissions', 'status') && ! array_key_exists('status', $row->getAttributes())) {
                $row->status = $row->is_approved ? 'approved' : 'pending';
            }
            if (empty($row->start_date) && ! empty($row->date_permission)) {
                $row->start_date = $row->date_permission;
            }
            if (empty($row->end_date) && ! empty($row->start_date)) {
                $row->end_date = $row->start_date;
            }
            if (empty($row->duration_days)) {
                $row->duration_days = 1;
            }
        });
    }

    protected $casts = [
        'is_approved' => 'boolean',
        'leave_policy_snapshot' => 'array',
        'leave_type_id' => 'integer',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'duration_days' => 'integer',
        'dates_breakdown' => 'array',
        'cancellation_requested_at' => 'datetime',
    ];

    protected $hidden = ['image'];

    protected $appends = ['attachment_url', 'date_range_label'];

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->image ? '/api/submission-attachments/permission/'.$this->id : null;
    }

    public function getDateRangeLabelAttribute(): string
    {
        $start = $this->start_date ? $this->start_date->format('Y-m-d') : $this->date_permission;
        $end = $this->end_date ? $this->end_date->format('Y-m-d') : $start;

        if ($start && $end && $start !== $end) {
            return "{$start} s/d {$end} ({$this->duration_days} hari kerja)";
        }

        return "{$start} ({$this->duration_days} hari kerja)";
    }

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'date_permission',
        'start_date',
        'end_date',
        'duration_days',
        'dates_breakdown',
        'reason',
        'image',
        'is_approved',
        'status',
        'cancellation_reason',
        'cancellation_requested_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function leaveBalanceLedgers()
    {
        return $this->hasMany(LeaveBalanceLedger::class);
    }
}
