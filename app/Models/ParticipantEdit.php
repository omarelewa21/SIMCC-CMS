<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParticipantEdit extends Model
{
    protected $table = 'participant_edits';

    protected $fillable = [
        'participant_id',
        'changes',
        'status',
        'reject_reason',
        'created_by_userid',
        'approved_by_userid',
    ];

    protected $casts = [
        'changes' => 'array', // Cast the 'changes' column as an array
    ];

    public function participant()
    {
        return $this->belongsTo(Participant::class, 'participant_id');
    }
}
