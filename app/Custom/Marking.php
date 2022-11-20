<?php

namespace App\Custom;

use App\Models\Competition;

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
                        'level_id'              => $level->id,
                        'name'                  => $level->name,
                        'level_ready'           => $this->getCompetitionLevelReady($level),
                        'total_participants'    => $totalParticipants,
                        'absentees_count'       => $absenteesQuery->count(),
                        'absentees'             => $absenteesQuery->inRandomOrder()->limit(10)->pluck('participants.name'),
                        'country_group'         => $countryGroup->pluck('display_name')->toArray()
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
     * Check if all levels are ready for computing
     * 
     * @param App\Models\Competition $competition
     * 
     * @return bool
     */
    public function checkIfShouldChangeMarkingGroupStatus (Competition $competition) {
        foreach($competition->rounds as $round){
            foreach($round->levels as $level){
                if(!$this->getCompetitionLevelReady($level)){
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * check if level is ready for computing
     * 
     * @param App\Models\CompetitionLevel $level
     * 
     * @return bool
     */
    private function getCompetitionLevelReady($level){
        $numberOfTasksIds = $level->collection->sections->sum('count_tasks');
        $numberOfCorrectAnswersWithMarks = $level->taskMarks()->join('task_answers', function ($join) {
            $join->on('competition_tasks_mark.task_answers_id', 'task_answers.id')->whereNotNull('task_answers.answer');
        })->select('task_answers.task_id')->distinct()->count();
        return $numberOfTasksIds === $numberOfCorrectAnswersWithMarks;
    }
}
