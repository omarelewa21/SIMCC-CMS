<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionRoundsAwards extends Base
{
    use HasFactory;

    protected $table = "competition_rounds_awards";
    protected $guarded = [];

    public function competitionRounds () {
        return $this->belongsTo(CompetitionRoundsAwards::class,'id','round_id');
    }
}
