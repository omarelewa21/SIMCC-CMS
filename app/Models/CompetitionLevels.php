<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionLevels extends Model
{
    use HasFactory;

    protected $table = "competition_levels";
    protected $guarded =[];

    public function rounds () {
        return $this->belongsTo(CompetitionRounds::class,'round_id','id');
    }

    public function groups () {
        return $this->hasMany(CompetitionMarkingGroup::class,'competition_level_id','id');
    }

    public function collection () {
        return $this->hasOne(Collections::class,'id','collection_id');
    }

    public function taskMarks () {
        return $this->hasMany(CompetitionTasksMark::class,'level_id','id');
    }

    public function taskDifficultyGroup () {
        return $this->hasMany(CompetitionTaskDifficulty::class,'level_id','id');
    }

    public function participantsAnswersUploaded () {
        return $this->hasMany(ParticipantsAnswer::class,'level_id','id');
    }

    public function setGradesAttribute ($value) {
        $value = array_unique($value);
        return $this->attributes['grades'] = json_encode($value);
    }

    public function getGradesAttribute ($value) {
        return json_decode($value);
}
}
