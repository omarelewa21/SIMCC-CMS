<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class CheatingParticipants extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'different_question_ids' => AsArrayObject::class,
    ];

    public function participant()
    {
        return $this->belongsTo(Participants::class, 'participant_index', 'index_no');
    }

    public function otherParticipant()
    {
        return $this->belongsTo(Participants::class, 'cheating_with_participant_index', 'index_no');
    }

    public function competition()
    {
        return $this->belongsTo(Competition::class);
    }

    public static function generateNewGroupId()
    {
        return static::orderBy('group_id', 'desc')->value('group_id') + 1;
    }
}
