<?php

namespace App\Services;

use App\Models\Collections;
use App\Models\Competition;
use App\Models\Grade;

class GradeService
{
    public static function getAllowedGradeNumbers(): array
    {
        return Grade::select('id')->pluck('id')->toArray();
    }

    public static function getAvailableCorrespondingGradesFromList(array $grades): array
    {
        return Grade::whereIn('id', $grades)->pluck('display_name')->toArray();
    }

    /**
     * Get Grades attached to levels that has verified collection
     *
     * @return array
     */
    public static function getGradesWithVerifiedCollections(Competition $competition): array
    {
        return $competition->levels()
            ->join('collection', 'collection.id', 'competition_levels.collection_id')
            ->where('collection.status', Collections::STATUS_VERIFIED)
            ->select('competition_levels.grades')
            ->pluck('grades')
            ->flatten()
            ->unique()
            ->toArray();
    }
}
