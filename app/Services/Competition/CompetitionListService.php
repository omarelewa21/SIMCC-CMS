<?php

namespace App\Services\Competition;

use App\Abstracts\GetList;
use App\Models\Competition;
use Illuminate\Database\Eloquent\Builder;

class CompetitionListService extends GetList
{
    protected function getModel(): string
    {
        return Competition::class;
    }

    protected function getWithRelations(): array
    {
        if(auth()->user()->hasRole(['Super Admin', 'Admin'])) {
            return $this->getAdminWithRelations();
        }
        return $this->getNormalUserWithRelations();
    }

    protected function getAdminWithRelations(): array
    {
        return [
            'competitionOrganization',
            'overallAwardsGroups.overallAwards',
            'rounds.roundsAwards',
            'rounds.levels' => fn ($query) => $query->orderBy('id'),
        ];
    }

    protected function getNormalUserWithRelations(): array
    {
        return [
            'competitionOrganization' => function ($query) {
                $query->where(['organization_id' => auth()->user()->organization_id])
                    ->where('country_id', auth()->user()->country_id);
            }
        ];
    }

    protected function getRespectiveUserModelQuery(): Builder
    {
        $query = Competition::query();
        if(!auth()->user()->hasRole(['Super Admin', 'Admin'])) {
            $query->where('status', 'active');
        }

        return $query->has('competitionOrganization');
    }

    protected function getFormat(): Builder
    {
        return (clone $this->baseQueryForFilters)->select('format as filter_id', 'format as filter_name');
    }
}
