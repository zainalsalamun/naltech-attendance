<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use \App\Models\Concerns\BelongsToCompany;
    use HasApiTokens, HasFactory, Notifiable;

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'position',
        'department',
        'face_embedding',
        'image_url',
        'fcm_token',
        'shift_id',
        'company_id',
        'employee_number',
        'organization_unit_id',
        'manager_id',
        'employment_status',
        'joined_on',
        'left_on',
        'deactivated_at',
        'archived_at',
        'version',
    ];

    public function organizationUnit()
    {
        return $this->belongsTo(OrganizationUnit::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function leaveBalances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function isEmploymentActive(): bool
    {
        // Unmigrated environments retain legacy authentication; lifecycle routes fail closed.
        return ! $this->archived_at && (! array_key_exists('employment_status', $this->attributes)
            || $this->employment_status === 'active');
    }

    public function setEmployeeNumberAttribute($value): void
    {
        $this->attributes['employee_number'] = is_string($value) && trim($value) !== ''
            ? strtoupper(trim($value)) : null;
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'auth_epoch',
        'face_embedding',
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'joined_on' => 'date:Y-m-d',
            'left_on' => 'date:Y-m-d',
            'deactivated_at' => 'datetime',
            'archived_at' => 'datetime',
            'auth_epoch' => 'integer',
            'version' => 'integer',
        ];
    }
}
