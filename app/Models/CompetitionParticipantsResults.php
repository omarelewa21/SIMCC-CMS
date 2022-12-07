<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionParticipantsResults extends Model
{
    use HasFactory;

    protected $table = 'competition_participants_results';
    protected $guarded = [];

    public $timestamps = false;

    public function getGlobalRankAttribute($value)
    {
        return sprintf("%s %s", $this->award, $value);
    }

    public function participant()
    {
        return $this->belongsTo(Participants::class,'participant_index','index_no');
    }
}
