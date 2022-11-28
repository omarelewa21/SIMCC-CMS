<?php

namespace App\Custom;

use App\Models\Competition;
use App\Models\CompetitionLevels;

class Marking
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
        $countries = $competition->groups->load('countries:id,display_name')->pluck('countries');

        $rounds = $competition->rounds->mapWithKeys(function ($round) use($countries){
            $levels = $round->levels->mapWithKeys(function ($level) use($countries){
                $levels = [];
                foreach($countries as $countryGroup){
                    $countriesParticipants = $level->participants()->whereIn('participants.country_id', $countryGroup->pluck('id')->toArray());
                    $totalParticipants = $countriesParticipants->count();
                    $absenteesQuery = $countriesParticipants
                                        ->where('participants.status', 'absent')
                                        ->whereIn('participants.country_id', $countryGroup->pluck('id')->toArray())
                                        ->select('participants.name')->distinct();

                    $levels[$level->id][] = [
                        'level_id'                      => $level->id,
                        'name'                          => $level->name,
                        'level_is_ready_to_compute'     => $this->isLevelReadyToCompute($level),
                        'computing_status'              => $level->computing_status,
                        'compute_progress_percentage'   => $level->compute_progress_percentage,
                        'compute_error_message'         => $level->compute_error_message,
                        'total_participants'            => $totalParticipants,
                        'absentees_count'               => $absenteesQuery->count(),
                        'absentees'                     => $absenteesQuery->inRandomOrder()->limit(10)->pluck('participants.name'),
                        'country_group'                 => $countryGroup->pluck('display_name')->toArray()
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
    public function isLevelReadyToCompute(CompetitionLevels $level){
        $numberOfTasksIds = $level->collection->sections->sum('count_tasks');
        $numberOfCorrectAnswersWithMarks = $level->taskMarks()->join('task_answers', function ($join) {
            $join->on('competition_tasks_mark.task_answers_id', 'task_answers.id')->whereNotNull('task_answers.answer');
        })->select('task_answers.task_id')->distinct()->count();

        if($numberOfTasksIds === $numberOfCorrectAnswersWithMarks){
            return $level->participantsAnswersUploaded()->count() > 0;
        };

        return false;
    }
}
