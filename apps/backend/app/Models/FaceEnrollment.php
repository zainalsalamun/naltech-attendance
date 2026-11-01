<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaceEnrollment extends Model
{
    use Concerns\BelongsToCompany;

    protected $guarded = ['id'];

    protected $hidden = ['photo_path'];

    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function summary(): array
    {
        return $this->only(['id', 'status', 'review_reason', 'created_at', 'reviewed_at']);
    }
}
