<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionRounds extends Model
{
    use HasFactory;

    Protected $table = 'competition_rounds';
    protected $guarded = [];


    public function competition () {
        return $this->belongsTo(Competition::class,'competition_id','id');
    }

    public function levels () {
        return $this->hasMany(CompetitionLevels::class,'round_id','id');
    }

    public function roundsAwards () {
        return $this->hasMany(CompetitionRoundsAwards::class,'round_id','id');
    }

    public function roundOverallAwards () {
        return $this->hasOne(CompetitionOverallAwards::class,'round_id','id');
    }
}
