<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\CompetitionLevels;
use Illuminate\Validation\ValidationException;

class ParticipantService
{
    public static function getParticipantLevelByGrade(Competition $competition, $grade): CompetitionLevels|null
    {
        $level = $competition->levels()->with('collection')->get()->first(function ($level) use ($grade) {
            if(is_string($grade) && str_contains($grade, 'Grade')){
                $grade = str_replace('Grade', '', $grade);
            }
            return in_array($grade, $level->grades);
        });
        return $level;
    }

    public static function getLevelsForGradeSet(Competition $competition, array $grades, bool $withTasks = false): array
    {
        $levels = [];
        foreach($grades as $grade){
            $level = self::getParticipantLevelByGrade($competition, $grade);
            if($level){
                if($withTasks){
                    $level->tasks = $level->collection->sections()->pluck('tasks')
                        ->map(function($taskCollection) {
                            return collect($taskCollection->toArray())->pluck('task_id')->flatten();
                        })->flatten()->sort();
                }
                $levels[$grade] = $level;
            } else {
                throw ValidationException::withMessages(["No level found for grade '$grade', please include this grade in competition levels first."]);
            }
        }
        return $levels;
    }
}
