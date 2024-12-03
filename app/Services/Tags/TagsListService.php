<?php

namespace App\Services\Tags;

use App\Abstracts\GetList;
use App\Models\DomainsTags;
use Illuminate\Database\Eloquent\Builder;

class TagsListService extends GetList
{
    protected function getModel(): string
    {
        return DomainsTags::class;
    }

    protected function getWithRelations(): array
    {
        return ['domain'];
    }

    protected function getDomains(): Builder
    {
        return $this->baseQueryForFilters->whereNull('domain_id')->where('is_tag', 0)
            ->select('id as filter_id', 'name as filter_name');
    }

    protected function getTags(): Builder
    {
        return $this->baseQueryForFilters->whereNull('domain_id')->where('is_tag', 1)
            ->select('id as filter_id', 'name as filter_name')
            ->orderBy('id', 'DESC');
    }

    protected function getDomainsWithTopics(): Builder
    {
        return $this->baseQueryForFilters->whereNull('domain_id')->where('is_tag', 0)
                ->with('topics:id,domain_id,name')
                ->select('id', 'name');
    }
}
