<?php

namespace App\Services;

class GradeService
{
    const ALLOWED_GRADE_NUMBERS = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
    const AvailableGrades = [
        0 => 'test grade',
        1 => 'Grade 1',
        2 => 'Grade 2',
        3 => 'Grade 3',
        4 => 'Grade 4',
        5 => 'Grade 5',
        6 => 'Grade 6',
        7 => 'Grade 7',
        8 => 'Grade 8',
        9 => 'Grade 9',
        10 => 'Grade 10',
        11 => 'Grade 11',
        12 => 'Grade 12',
        13 => 'Grade 11 - 12',
        14 => 'K1',
        15 => 'K2',
    ];

    public static function getAvailableCorrespondingGradesFromList(array $grades): array
    {
        $availableGrades = [];
        foreach ($grades as $grade) {
            if (array_key_exists($grade, self::AvailableGrades)) {
                $availableGrades[] = [
                    'id' => $grade,
                    'name' => self::AvailableGrades[$grade]
                ];
            }
        }
        return $availableGrades;
    }
}
