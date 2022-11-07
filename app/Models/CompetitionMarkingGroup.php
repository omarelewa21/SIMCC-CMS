<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompetitionMarkingGroup extends Base
{
    use HasFactory;

    protected $table = "competition_marking_group";

    protected $fillable = ['name', 'competition_id', 'created_by_userid', 'status', 'last_modified_userid'];

    // protected $appends = [
    //     'created_by',
    //     'last_modified_by',
    //     'total_participants',
    //     'school_participants',
    //     'private_participants',
    //     'school_participants_answers_count',
    //     'private_participants_answers_count',
    //     'participantsComputed',
    //     'particitpants_index_no_list',
    //     'country_name'
    // ];


    public function level () {
        return $this->belongsTo(CompetitionLevels::class,'competition_level_id','id');
    }

    public function getCountryGroupAttribute ($value) {
        return json_decode($value);
    }

    public function getCountryNameAttribute ($value) {
        $countries = Countries::all()->mapWithKeys(function ($item, $key) {
          return [$item['id'] => $item['display_name']];
        });

        $countries_id = $this->getCountryGroupAttribute($this->attributes['country_group']);

        return collect($countries_id)->map(function ($item) use($countries) {
            return $countries[$item];
        });
    }

    public function setCountryGroupAttribute ($value) {
        $this->attributes['country_group'] = json_encode($value);
    }

    public function getTotalParticipantsAttribute () {
        return count($this->participants());
    }

    public function getSchoolParticipantsAttribute () {
        return count($this->participants()->where('tuition_centre_id',null));
    }

    public function getSchoolParticipantsAnswersCountAttribute () {
        $competition_group_index = $this->participants()->whereNull('tuition_centre_id')->pluck('index_no')->toArray();
        return ParticipantsAnswer::whereIn('participant_index',$competition_group_index)->distinct('participant_index')->count();
    }

    public function getPrivateParticipantsAttribute () {
        return count($this->participants()->where('tuition_centre_id',true));
    }

    public function getPrivateParticipantsAnswersCountAttribute () {
        $competition_group_index = $this->participants()->whereNotNull('tuition_centre_id')->pluck('index_no')->toArray();
        return ParticipantsAnswer::whereIn('participant_index',$competition_group_index)->distinct('participant_index')->count();
    }

    public function getParticipantsComputedAttribute () {
        $competition_group_index = $this->participants()->pluck('id')->toArray();
        $participantsUploadedAnswers = ParticipantsAnswer::whereIn('participant_index',$competition_group_index)->distinct('participant_index')->count();
        $totaTasks = CompetitionLevels::find($this->attributes['competition_level_id'])->collection->sections->pluck('section_task')->flatten()->count();
        $totalParticipants = $this->getPrivateParticipantsAttribute()  + $this->getSchoolParticipantsAttribute();
        $totalAnswers = $totaTasks * $totalParticipants;
        $totalMarked = $participantsUploadedAnswers > 0 ? floor($totalAnswers / $participantsUploadedAnswers) : 0;
        return $totalMarked;
    }

    public function getParticitpantsIndexNoListAttribute () {
        return $this->participants()->pluck('index_no');
    }

    private function participants() {
        $countries = $this->getCountryGroupAttribute($this->attributes['country_group']);
        $competitionLevel = CompetitionLevels::find($this->attributes['competition_level_id']);
        $competitionLevelGrades = CompetitionLevels::find($this->attributes['competition_level_id'])->grades;
        $competition =$competitionLevel->rounds->competition;
        $competitionOrganizationIds = $competition->competitionOrganization->pluck('id')->toArray();
        $participants = Participants::whereIn('country_id',$countries)->whereIn('competition_organization_id',$competitionOrganizationIds)->whereIn('grade',$competitionLevelGrades)->get();
        return $participants;
    }
}
