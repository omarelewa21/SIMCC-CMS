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
        return CompetitionLevels::where('collection_id', $collectionId)
            ->join('competition_rounds', 'competition_rounds.id', 'competition_levels.round_id')
            ->join('competition', 'competition.id', 'competition_rounds.competition_id')
            ->where('competition.status', $status)
            ->exists();
    }
}
