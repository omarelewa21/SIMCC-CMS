<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class CompetitionParticipantsResults extends Model
{
    use HasFactory;

    protected $table = 'competition_participants_results';
    protected $guarded = [];

    protected $casts = [
        'report'    => AsArrayObject::class,
    ];

    public $timestamps = false;

    public function competitionLevel()
    {
        return $this->belongsTo(CompetitionLevels::class, 'level_id');
    }

    public function participant()
    {
        return $this->belongsTo(Participants::class,'participant_index','index_no');
    }
}
