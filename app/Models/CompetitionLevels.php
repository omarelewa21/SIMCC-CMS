<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompetitionLevels extends Model
{
    use HasFactory;

    const STATUS_NOT_STARTED  = "Not Started";
    const STATUS_In_PROGRESS  = "In Progress";
    const STATUS_FINISHED     = "Finished";
    const STATUS_BUG_DETECTED = "Bug Detected";

    protected $table = "competition_levels";
    protected $guarded =[];

    protected $casts = [
        'grades' => 'array',
    ];

    public function rounds () {
        return $this->belongsTo(CompetitionRounds::class,'round_id','id');
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

    public function participants()
    {
        return $this->rounds->competition->participants()->whereIn('participants.grade', $this->grades);
    }

    public function updateStatus($status, $error_message=null)
    {
        $this->update([
            'computing_status'              => $status,
            'compute_error_message'         => $error_message,
            'compute_progress_percentage'   => $status === self::STATUS_In_PROGRESS ? 0 : $this->compute_progress_percentage
        ]);
    }

    public function maxPoints()
    {
        return $this->taskMarks()->sum('competition_tasks_mark.marks');
    }

    public function participantResults()
    {
        return $this->hasMany(CompetitionParticipantsResults::class, 'level_id');
    }
}
