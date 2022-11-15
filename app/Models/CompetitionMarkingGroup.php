<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompetitionMarkingGroup extends Base
{
    use HasFactory;

    protected $table = "competition_marking_group";

    protected $fillable = ['name', 'competition_id', 'created_by_userid', 'status', 'last_modified_userid'];

    protected $appends = [
        'created_by',
        'last_modified_by',
        'total_participants_count',
        // 'school_participants',
        // 'private_participants',
        // 'school_participants_answers_count',
        // 'private_participants_answers_count',
        // 'participants_computed',
        // 'particitpants_index_no_list',
        // 'country_name'
    ];


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

    public function getTotalParticipantsCountAttribute () {
        return $this->participants()->count();
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

    public function participants() {
        return $this->competition->participants()->whereIn('participants.country_id', $this->countries->pluck('id')->toArray());
    }

    public function competition(){
        return $this->belongsTo(Competition::class);
    }

    public function countries(){
        return $this->belongsToMany(Countries::class, 'competition_marking_group_country', 'marking_group_id', 'country_id');
    }

    public function undoComputedResults($groupStatus) {
        if(isset($groupStatus)) {
            $this->status = $groupStatus;
            $this->save();
        }

        $particitpants_index_no_list = $this->particitpants_index_no_list->toArray();

        if(count($particitpants_index_no_list) > 0) {
            CompetitionParticipantsResults::whereIn('participant_index', $particitpants_index_no_list)->delete();
            Participants::whereIn('index_no', $particitpants_index_no_list)->update(['status' => 'active']);
        }
    }
}
