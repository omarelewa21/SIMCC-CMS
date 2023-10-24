<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class CompetitionMarkingGroup extends Base
{
    use HasFactory;

    protected $table = "competition_marking_group";

    protected $fillable = ['name', 'competition_id', 'created_by_userid', 'status', 'last_modified_userid'];

    protected $appends = [
        'created_by',
        'last_modified_by'
    ];

    public static function booted()
    {
        parent::booted();

        static::deleting(function($record) {
            DB::table('competition_marking_group_country')->where('marking_group_id', $record->id)->delete();
        });
    }

    public function competition(){
        return $this->belongsTo(Competition::class);
    }

    public function countries(){
        return $this->belongsToMany(Countries::class, 'competition_marking_group_country', 'marking_group_id', 'country_id');
    }

    public function participants() {
        return $this->competition->participants()->whereIn('participants.country_id', $this->countries->pluck('id')->toArray());
    }

    public function getTotalParticipantsCountAttribute () {
        return $this->competition()
                ->join('competition_organization as c_o', 'competition.id', 'c_o.competition_id')
                ->join('participants', 'participants.competition_organization_id', 'c_o.id')
                ->whereIn('participants.country_id', $this->countries->pluck('id')->toArray())
                ->select('participants.index')->distinct()->count();
    }

    public function getSchoolParticipantsAttribute () {
        return $this->participants()->where('tuition_centre_id',null)->count();
    }

    public function getSchoolParticipantsAnswersCountAttribute () {
        $competition_group_index = $this->participants()->whereNull('tuition_centre_id')->pluck('index_no')->toArray();
        return ParticipantsAnswer::whereIn('participant_index', $competition_group_index)->distinct('participant_index')->count();
    }

    public function getPrivateParticipantsAttribute () {
        return $this->participants()->where('tuition_centre_id',true)->count();
    }

    public function getPrivateParticipantsAnswersCountAttribute () {
        $competition_group_index = $this->participants()->whereNotNull('tuition_centre_id')->pluck('index_no')->toArray();
        return ParticipantsAnswer::whereIn('participant_index',$competition_group_index)->distinct('participant_index')->count();
    }

    public function getParticipantsComputedAttribute () {
        $competition_group_index = $this->participants()->pluck('participants.id')->toArray();
        $participantsUploadedAnswers = ParticipantsAnswer::whereIn('participant_index', $competition_group_index)->distinct('participant_index')->count();
        $totalTasks = $this->competition->totalTasksCount();
        $totalParticipants = $this->getPrivateParticipantsAttribute()  + $this->getSchoolParticipantsAttribute();
        $totalAnswers = $totalTasks * $totalParticipants;
        $totalMarked = $participantsUploadedAnswers > 0 ? floor($totalAnswers / $participantsUploadedAnswers) : 0;
        return $totalMarked;
    }

    public function getParticitpantsIndexNoListAttribute () {
        return $this->participants()->pluck('index_no');
    }

    public function levelGroupCompute($levelId=null)
    {
        if($levelId) {
            return $this->hasOne(LevelGroupCompute::class, 'group_id')->where('level_id', $levelId);
        }
        return $this->hasMany(LevelGroupCompute::class, 'group_id');
    }
}
