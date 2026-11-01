<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrectionEvent extends Model
{
    use \App\Models\Concerns\BelongsToCompany;
    use HasFactory;

    public $timestamps = false;

    protected $casts = [
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    protected $fillable = [
        'company_id',
        'attendance_id',
        'actor_id',
        'reason',
        'snapshot',
        'created_at',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
