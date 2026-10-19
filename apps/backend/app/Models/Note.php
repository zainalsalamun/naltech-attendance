<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Note extends Model
{
    use HasFactory;
    use \App\Models\Concerns\BelongsToCompanyUser;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected $fillable = [
        'title',
        'note',

    ];
}
