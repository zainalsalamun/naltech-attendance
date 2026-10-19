<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use \App\Models\Concerns\BelongsToCompany;
    protected $fillable = [
        'name',
        'time_in',
        'time_out',
    ];
}
