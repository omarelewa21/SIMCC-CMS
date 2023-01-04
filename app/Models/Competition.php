<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;
use Illuminate\Support\Arr;

class Competition extends Base
{
    use HasFactory, Filterable;

    protected $table = "competition";

    protected $with = ['rounds'];

    protected $hidden = ['created_by_userid','last_modified_userid'];

    protected $appends = [
        'created_by',
        'last_modified_by',
        'award_type_name',
        'compute_status'
        // 'generate_report_btn'
    ];

    private static $whiteListFilter = [
        'id',
        'status',
        'format',
        'name'
    ];

    protected $fillable = [
        "name",
        'global_registration_date',
        'global_registration_end_date',
        "competition_start_date",
        "competition_end_date",
        "competition_mode",
        "parent_competition_id",
        "allowed_grades",
        "alias",
        "format",
        "status",
        "created_by_userid",
        "difficulty_group_id",
        "award_type",
        "min_points",
        "default_award_name"
    ];

    public function competitionOrganization()
    {
        return $this->hasMany(CompetitionOrganization::class,"competition_id",'id');
    }

    public function taskDifficultyGroup () {
        return $this->hasOne(TaskDifficultyGroup::class,'id','difficulty_group_id');
    }

    public function taskDifficulty () {
        return $this->hasManyThrough(TaskDifficulty::class,TaskDifficultyGroup::class,'id','difficulty_groups_id','difficulty_group_id','id');
    }

    public function rounds ()
    {
        return $this->hasMany(CompetitionRounds::class, "competition_id");
    }

    public function groups () {
        return $this->hasMany(CompetitionMarkingGroup::class, 'competition_id');
    }

    public function overallAwardsGroups () {
        return $this->hasMany(CompetitionOverallAwardsGroups::class, 'competition_id');
    }

    public function participants () {
        return $this->hasManyThrough(Participants::class, CompetitionOrganization::class, 'competition_id', 'competition_organization_id', 'id', 'id');
    }

    public function setAllowedGradesAttribute ($value) {
        $this->attributes['allowed_grades'] = json_encode($value);
    }

    public function getAllowedGradesAttribute ($value) {
        return json_decode($value);
    }

    public function getComputeStatusAttribute()
    {
        $statusses = $this->rounds()->join('competition_levels', 'competition_rounds.id', 'competition_levels.round_id')
            ->pluck('competition_levels.computing_status')->unique();
        
        if(count($statusses->toArray()) === 0 && $statusses->contains(CompetitionLevels::STATUS_FINISHED)){
            return CompetitionLevels::STATUS_FINISHED;
        }
        if(count($statusses->toArray()) === 0 || $statusses->contains(CompetitionLevels::STATUS_NOT_STARTED)){
            return CompetitionLevels::STATUS_NOT_STARTED;
        }
        if($statusses->contains(CompetitionLevels::STATUS_In_PROGRESS) || $statusses->contains(CompetitionLevels::STATUS_BUG_DETECTED)){
            return CompetitionLevels::STATUS_In_PROGRESS;
        }
        return CompetitionLevels::STATUS_FINISHED;
    }

    public function getGenerateReportBtnAttribute () {
        $levels = $this->rounds->pluck('levels')->flatten()->pluck('id');
        $found = CompetitionMarkingGroup::whereIn('competition_level_id',$levels)->count() > 0 ?  1 : 0;

        return $found;
    }

    public function getAwardTypeNameAttribute () {
        switch($this->award_type) {
            case 0 :
               return 'percentage';
            case 1:
                return 'position';
        }
    }

    public function getActiveParticipantsByCountry($country_id)
    {   
        return $this->participants()->where('country_id', $country_id)->get();
    }

    public function totalTasksCount()
    {
        $collectionIds = 
            $this->rounds()->join('competition_levels as cl', 'cl.round_id', 'competition_rounds.id')
                ->join('collection', 'collection.id', 'cl.collection_id')
                ->select('collection.id as id')->distinct()
                ->pluck('id')->toArray();

        $sections = CollectionSections::distinct()->whereIn('collection_id', $collectionIds)->get();
        $count = 0;
        foreach($sections as $section){
            if($section->count_tasks){
                $count += $section->count_tasks;
            }
        }
        return $count;
    }
}
