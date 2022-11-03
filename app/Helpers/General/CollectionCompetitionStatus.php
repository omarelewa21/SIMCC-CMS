<?php


namespace App\Helpers\General;
use App\Models\CompetitionLevels;


class CollectionCompetitionStatus
{
    public static function CheckStatus ($collectionId,$status) {
        $activeCompetition = CompetitionLevels::with(['rounds.competition' => function ($query) use($status) {
            $query->where('status',$status);
        }
        ])->where('collection_id',$collectionId)->get()->pluck('rounds.competition')->filter()->count();

        return $activeCompetition;
    }
}
