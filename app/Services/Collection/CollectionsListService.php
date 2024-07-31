<?php

namespace App\Services\Collection;

use App\Abstracts\GetList;
use App\Models\Collections;
use Illuminate\Database\Eloquent\Builder;

class CollectionsListService extends GetList
{
    protected function getModel(): string
    {
        return Collections::class;
    }

    protected function getCompetitions(): Builder
    {
        return (clone $this->baseQueryForFilters)
            ->join('competition_levels', 'competition_levels.collection_id', '=', 'collection.id')
            ->join('competition_rounds', 'competition_rounds.id', '=', 'competition_levels.round_id')
            ->join('competition', 'competition.id', '=', 'competition_rounds.competition_id')
            ->select('competition.id as filter_id', 'competition.name as filter_name');
    }

    protected function getWithRelations(): array
    {
        return [
            'reject_reason:reject_id,reason,created_at,created_by_userid',
            'reject_reason.user:id,username',
            'reject_reason.role:roles.name',
            'tags:id,name',
            'gradeDifficulty',
            'sections',
        ];
    }
}
