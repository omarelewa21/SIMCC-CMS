<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use eloquentFilter\QueryFilter\ModelFilters\Filterable;

class Competition extends Base
{
    use HasFactory, Filterable;

    protected $table = "competition";

    protected $with = ['rounds'];

    protected $hidden = ['created_by_userid','last_modified_userid'];

    protected $appends = ['created_by','last_modified_by','award_type_name'];

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
        return $this->hasMany(CompetitionRounds::class,"competition_id",'id');
    }

    public function overallAwardsGroups () {
        return $this->hasMany(CompetitionOverallAwardsGroups::class,'competition_id','id');
    }


    public function participants () {
        return $this->hasManyThrough(Participants::class,CompetitionOrganization::class,'competition_id','competition_organization_id','id','id');
    }

    public function setAllowedGradesAttribute ($value) {
        $this->attributes['allowed_grades'] = json_encode($value);
    }

    public function getAllowedGradesAttribute ($value) {
        return json_decode($value);
    }

    public function getAwardTypeNameAttribute () {
        switch($this->award_type) {
            case 0 :
               return 'percentage';
            case 1:
                return 'position';
        }
    }
}
