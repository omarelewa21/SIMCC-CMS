<?php

namespace App\Helpers;

use App\Models\Competition;
use App\Models\CompetitionLevels;
use Illuminate\Validation\ValidationException;

class AnswerUploadHelper
{

    /**
     * Map CSV grades to system grades
     */
    const CSV_GRADES_TO_SYSTEM_GRADES = [
        "P1-G1" => "1",
        "P2-G2" => "2",
        "P3-G3" => "3",
        "P4-G4" => "4",
        "P5-G5" => "5",
        "P6-G6" => "6",
        "S1-G7" => "7",
        "S2-G8" => "8",
        "S3-G9" => "9",
        "S4-G10" => "10",
        "JC-G11-G12" => "11",
        "JC-G11" => "11",
        "JC-G12" => "12",
        "K1" => "14",
        "K2" => "15",
    ];

    /**
     * Get the level for a participant based on their grade
     * 
     * @param Competition $competition
     * @param $grade
     * 
     * @return CompetitionLevels|null
     */
    public static function getParticipantLevelByGrade(Competition $competition, string $grade): CompetitionLevels|null
    {
        return $competition->levels()->with('collection')
            ->get()
            ->first(fn ($level) => in_array($grade, $level->grades));
    }

    /**
     * Get the levels for a participant based on their grade
     * 
     * @param Competition $competition
     * @param array $grades
     * @param bool $withTasks
     * 
     * @return array
     */
    public static function getLevelsForGradeSet(Competition $competition, array $grades, bool $withTasks = false): array
    {
        $levels = [];
        foreach($grades as $grade){
            $systemGrade = self::translateCSVGradeToSystemGrade($grade);
            $level = self::getParticipantLevelByGrade($competition, $systemGrade);
            if($level){
                if($withTasks){
                    $level->tasks = $level->collection->sections()->pluck('tasks')
                        ->map(function($taskCollection) {
                            return collect($taskCollection->toArray())->pluck('task_id')->flatten();
                        })->flatten()->sort();
                }
                $levels[$grade]['level'] = $level;
                $levels[$grade]['grade'] = $systemGrade;
            } else {
                throw ValidationException::withMessages(["No level found for grade '$grade', please include this grade in competition levels first."]);
            }
        }
        return $levels;
    }

    private static function translateCSVGradeToSystemGrade(string $grade)
    {
        if(is_string($grade) && array_key_exists($grade, self::CSV_GRADES_TO_SYSTEM_GRADES)) {
            $grade = self::CSV_GRADES_TO_SYSTEM_GRADES[$grade];
        }
        elseif(is_string($grade) && str_contains($grade, 'Grade')) {
            $grade = str_replace('Grade', '', $grade);
        }
        return $grade;
    }

    public static function getTrimmedAnswer($answer)
    {
        return is_numeric($answer)
            ? rtrim($answer, '.0')
            : $answer;
    }
}