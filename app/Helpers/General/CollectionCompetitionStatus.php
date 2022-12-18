<?php


namespace App\Helpers\General;
use App\Models\CompetitionLevels;


class CollectionCompetitionStatus
{
    /**
     * check if a collection is in a competition with specified status
     */
    public static function CheckStatus(int $collectionId, string $status): bool
    {
        $activeCompetition = CompetitionLevels::with(['rounds.competition' => function ($query) use($status) {
            $query->where('status', $status);
        }
        ])->where('collection_id', $collectionId)->pluck('rounds.competition')->filter()->count();

        return $activeCompetition > 0;
    }
}
