<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use \App\Models\Concerns\BelongsToCompanyUser;
    use HasFactory;

    public function getScheduleLabelAttribute(): string
    {
        return $this->checked_in_at ? ($this->scheduled_name ?: 'Tanpa shift tersimpan') : ($this->user?->shift?->name ?: 'Shift lama belum diatur');
    }

    public function getTimingLabelAttribute(): string
    {
        if (! $this->time_in) {
            return 'Belum masuk';
        }
        if ($this->checked_in_at) {
            return ! $this->scheduled_start_at ? 'Perlu tinjau' : ($this->checked_in_at > $this->scheduled_start_at ? 'Terlambat' : 'Tepat waktu');
        }
        $shift = $this->user?->shift;

        return ! $shift || ! $shift->time_in || ! $shift->time_out || $shift->time_out <= $shift->time_in ? 'Perlu tinjau' : ($this->time_in > $shift->time_in ? 'Terlambat' : 'Tepat waktu');
    }

    protected $casts = [
        'version' => 'integer',
    ];

    protected $fillable = [
        'user_id',
        'date',
        'time_in',
        'time_out',
        'latlon_in',
        'latlon_out',
        'schedule_assignment_id',
        'schedule_source',
        'scheduled_name',
        'scheduled_in',
        'scheduled_out',
        'attendance_timezone',
        'calendar_working',
        'scheduled_start_at',
        'scheduled_end_at',
        'checked_in_at',
        'checked_out_at',
        'version',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function correctionEvents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AttendanceCorrectionEvent::class)->orderByDesc('id');
    }
}
