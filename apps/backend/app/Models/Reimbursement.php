<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reimbursement extends Model
{
    use \App\Models\Concerns\BelongsToCompanyUser;
    protected $fillable = [
        'user_id',
        'date',
        'description',
        'amount',
        'image',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
