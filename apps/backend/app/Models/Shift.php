<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use \App\Models\Concerns\BelongsToCompany;

    public function users()
    {
        return $this->hasMany(User::class);
    }

    protected $fillable = [
        'name',
        'time_in',
        'time_out',
    ];
}
