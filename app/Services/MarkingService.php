<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionLevels;
use App\Models\Countries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MarkingService
{
    /**
     * Get mark list
     * 
     * @param App\Models\Competition $competition
     * 
     * @return array
     */
    public function markList(Competition $competition)
    {
        $competition->load('rounds.levels.levelGroupComputes', 'groups.countries:id,display_name');

        $countries = $competition->groups->load('countries:id,display_name')->pluck('countries', 'id');
        
        $rounds = $competition->rounds->mapWithKeys(function ($round) use($countries){
            $levels = $round->levels->mapWithKeys(function ($level) use($countries){
                $levels = [];
                foreach($countries as $group_id=>$countryGroup){
                    $totalParticipants  = $level->participants()->whereIn('participants.country_id', $countryGroup->pluck('id')->toArray())->count();
                    $markedParticipants = $level->participants()->whereIn('participants.country_id', $countryGroup->pluck('id')->toArray())
                                            ->where('participants.status', 'result computed')->count();
                    $absentees = $level->participants()->whereIn('participants.country_id', $countryGroup->pluck('id')->toArray())
                                        ->where('participants.status', 'absent')
                                        ->whereIn('participants.country_id', $countryGroup->pluck('id')->toArray())
                                        ->select('participants.name')->distinct()->get();
                    
                    $answersUploaded = $level->participantsAnswersUploaded()
                        ->join('participants', 'participants.index_no', 'participant_answers.participant_index')
                        ->whereIn('participants.country_id', $countryGroup->pluck('id')->toArray())
                        ->select('participant_answers.participant_index')->distinct()->count('participant_index');
                    
                    $levelGroupCompute = $level->levelGroupComputes->where('group_id', $group_id)->first(); 

                    $levels[$level->id][] = [
                        'level_id'                      => $level->id,
                        'name'                          => $level->name,
                        'level_is_ready_to_compute'     => $this->isLevelReadyToCompute($level),
                        'computing_status'              => $levelGroupCompute?->computing_status ?? 'Not Started',
                        'compute_progress_percentage'   => $levelGroupCompute?->compute_progress_percentage ?? 0,
                        'compute_error_message'         => $levelGroupCompute?->compute_error_message ?? null,
                        'total_participants'            => $totalParticipants,
                        'answers_uploaded'              => $answersUploaded,
                        'marked_participants'           => $markedParticipants,
                        'absentees_count'               => $absentees->count(),
                        'absentees'                     => $absentees->count() > 10 ? $absentees->random(10)->pluck('name') : $absentees->pluck('name'),
                        'country_group'                 => $countryGroup->pluck('display_name')->toArray(),
                        'marking_group_id'              => $group_id
                    ];
                }
                return $levels;
            });
            return [$round['name'] => $levels];
        });
        return [
            "competition_name" => $competition['name'],
            "rounds"           => $rounds
        ];
    }

    /**
     * Check if competition are ready for computing
     * 
     * @param App\Models\Competition $competition
     * 
     * @return bool
     */
    public function isCompetitionReadyForCompute(Competition $competition) {
        foreach($competition->rounds as $round){
            foreach($round->levels as $level){
                if(!$this->isLevelReadyToCompute($level)){
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * check if level is ready for computing - returns true if (all tasks has corresponding true answers and level has uploaded answers)
     * 
     * @param App\Models\CompetitionLevel $level
     * 
     * @return bool
     */
    public static function isLevelReadyToCompute(CompetitionLevels $level){
        $level->load('collection.sections', 'rounds');
        $levelTaskIds = $level->collection->sections
            ->pluck('section_task')->flatten()->pluck("id");

        $numberOfTasksIds = $levelTaskIds->count('count_tasks');

        $numberOfCorrectAnswersWithMarks = $level->taskMarks()->join('task_answers', function ($join) {
            $join->on('competition_tasks_mark.task_answers_id', 'task_answers.id')->whereNotNull('task_answers.answer');
        })
        ->whereIn('task_answers.task_id', $levelTaskIds)
        ->select('task_answers.task_id')->distinct()->count();
        // if($level->id == 380){
        //     $toDD1 = $level->collection->sections
        //         ->pluck('section_task')->flatten()->pluck("id")->toArray();
        //     $toDD2 = $level->taskMarks()->join('task_answers', function ($join) {
        //         $join->on('competition_tasks_mark.task_answers_id', 'task_answers.id')->whereNotNull('task_answers.answer');
        //     })->select('task_answers.task_id')->distinct()->get()->toArray();
        //     dd($toDD1, $toDD2);
        // }
        if($numberOfCorrectAnswersWithMarks >= $numberOfCorrectAnswersWithMarks){
            if($level->participantsAnswersUploaded()->count() > 0){
                if($level->rounds->roundsAwards()->count() > 0){
                    return true;
                }
            }
        };
        Log::info(sprintf("%s: %s %s %s %s", $level->id, $numberOfTasksIds, $numberOfCorrectAnswersWithMarks, $level->participantsAnswersUploaded()->count(), $level->rounds->roundsAwards()->count()));

        return false;
    }

    /**
     * get cut off points for participant results
     * 
     * @param Illuminate\Database\Eloquent\Collection $participantResults
     * 
     * @return array
     */
    public function getCutOffPoints($participantResults)
    {
        $participantAwards = $participantResults->pluck('award')->unique();
        $data = [];
        foreach($participantAwards as $award){
            $filteredParticipants = $participantResults->filter(fn($participantResult)=> $participantResult->award == $award);
            $data[$award]['max'] = $filteredParticipants->first()->points;
            $data[$award]['min'] = $filteredParticipants->last()->points;
        }
        return $data;
    }

    /**
     * get participants count (with and without answers) by country and grade
     * 
     * @param App\Models\Competition $competition
     * @param Illuminate\Http\Request $request
     * 
     * @return array
     */
    public static function getActiveParticipantsByCountryByGradeData(Competition $competition, Request $request)
    {       
        $countries = Countries::whereIn('id', $request->countries)->select('id', 'display_name')
            ->get()
            ->mapWithKeys(fn($country)=>[
                $country->id => [
                    'name' => $country->display_name,
                    'id'   => $country->id
                ]
            ]);

        $totalParticipants = $competition->participants()
            ->whereIn('participants.country_id', $request->countries)
            ->groupBy('participants.country_id', 'participants.grade')
            ->selectRaw('participants.country_id, participants.grade, count(participants.id) as total_participants')
            ->get();

        $totalParticipantsWithAnswer = $competition->participants()
            ->whereIn('participants.country_id', $request->countries)
            ->has('answers')
            ->groupBy('participants.country_id', 'participants.grade')
            ->selectRaw('participants.country_id, participants.grade, count(participants.id) as total_participants_with_answers')
            ->get();

        return [$countries, $totalParticipants, $totalParticipantsWithAnswer];
    }

    /**
     * get all participants count by country and grade
     * 
     * @param array $data by reference
     * @param Illuminate\Database\Eloquent\Collection $countries
     * @param Illuminate\Database\Eloquent\Collection $totalParticipants
     * 
     * @return void
     */
    public static function setTotalParticipantsByCountryByGrade(&$data, $countries, $totalParticipants)
    {
        foreach($totalParticipants as $participants){
            $country = $countries[$participants->country_id]['name'];

            static::inititializeCountryGrade($data, $participants->grade, $country);

            // Add total participants for each country and grade
            $data[$participants->grade][$country] = [
                'total_participants' => $participants->total_participants,
                'country'            => $country,
                'grade'              => $participants->grade
            ];

            // Add total participants for all grades per country
            $data['total'][$country]['total_participants'] += $participants->total_participants;

             // Add total participants for all countries per grade
            $data[$participants->grade]['total']['total_participants'] += $participants->total_participants;
        }
    }

    /**
     * get participants (with answers) and absentees count by country and grade
     * 
     * @param array $data by reference
     * @param Illuminate\Database\Eloquent\Collection $countries
     * @param Illuminate\Database\Eloquent\Collection $totalParticipantsWithAnswer
     * 
     * @return void
     */
    public static function setTotalParticipantsWithAnswersAndAbsentees(&$data, $countries, $totalParticipantsWithAnswer)
    {
        foreach($totalParticipantsWithAnswer as $participants){
            $country = $countries[$participants->country_id]['name'];
            // Add total participants with answers for each country and grade
            $data[$participants->grade][$country]['total_participants_with_answers']
                = $participants->total_participants_with_answers;

            // Add total absentees for each country and grade
            $data[$participants->grade][$country]['absentees']
                = $data[$participants->grade][$country]['total_participants'] - $participants->total_participants_with_answers;

            $data[$participants->grade]['total']['total_participants_with_answers'] += $participants->total_participants_with_answers;    // Add total participants with answers for all countries per grade
            $data[$participants->grade]['total']['absentees'] += $data[$participants->grade][$country]['absentees'];     // Add total absentees for all countries per grade

            // Add total participants for all grades per country
            $data['total'][$country]['total_participants_with_answers'] += $participants->total_participants_with_answers;
            $data['total'][$country]['absentees'] += $data[$participants->grade][$country]['absentees'];
        }
    }

    private static function inititializeCountryGrade(&$data, $grade, $country)
    {
        if(!isset($data[$grade]['total']['total_participants'])) {
            // Initialize total participants for all countries per grade
            $data[$grade]['total'] = [
                'total_participants' => 0,
                'total_participants_with_answers' => 0,
                'absentees' => 0,
                'grade'     => $grade
            ];
        }

        if(!isset($data['total'][$country])) {
            $data['total'][$country] = [
                'total_participants' => 0,
                'total_participants_with_answers' => 0,
                'absentees' => 0,
                'country'   => $country
            ];
        }
    }
}
