<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reimbursement extends Model
{
    use \App\Models\Concerns\BelongsToCompanyUser;
    use \App\Models\Concerns\HasSubmissionApproval;

    protected $hidden = ['image'];

    protected $appends = ['attachment_url'];

    public function getAttachmentUrlAttribute(): ?string
    {
        return $this->image ? '/api/submission-attachments/reimbursement/'.$this->id : null;
    }

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
