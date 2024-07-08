<?php

namespace App\Abstracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class GetList
{
    protected $baseQueryForFilters;

    public function __construct(protected Request $request)
    {
        $this->baseQueryForFilters = $this->getBaseQueryForFilters();
    }

    protected function getBaseQueryForFilters(): Builder
    {
        return $this->getModel()::distinct()->filter($this->request);
    }

    public function getWhatUserWants(): array|Collection|LengthAwarePaginator
    {
        return $this->request->filled('get_filter')
            ? $this->returnFilterOptions()
            : $this->returnTableData();
    }

    private function returnFilterOptions(): array|Collection
    {
        return $this->getFilterOptionsQuery()
            ->get()
            ->map(fn($item) => $item->setAppends([]));
    }

    protected function returnTableData(): LengthAwarePaginator
    {
        return $this->getRespectiveUserModelQuery()
            ->with($this->getWithRelations())
            ->filter($this->request)
            ->search($this->request->search ?? '')
            ->orderBy("{$this->getTable()}.updated_at", 'desc')
            ->paginate($this->request->limits ?? defaultLimit());
    }

    protected function getTags(): Builder
    {
        return (clone $this->baseQueryForFilters)
            ->join('taggables', function (JoinClause $join){
                $join->on('taggables.taggable_id', '=', "{$this->getTable()}.id")
                    ->where('taggables.taggable_type', $this->getModel());
                })
            ->join('domains_tags', function (JoinClause $join){
                $join->on('domains_tags.id', '=', 'taggables.domains_tags_id')
                    ->where('domains_tags.is_tag', 1);
            })
            ->select('domains_tags.id as filter_id', 'domains_tags.name as filter_name');
    }

    protected function getStatuses(): Builder
    {
        return (clone $this->baseQueryForFilters)->select("status as filter_id","status as filter_name");
    }

    protected function getTable(): string
    {
        return (new ($this->getModel()))->getTable();
    }

    protected abstract function getModel(): string;
    protected abstract function getFilterOptionsQuery(): Builder;
    protected abstract function getWithRelations(): array;
    protected abstract function getRespectiveUserModelQuery(): Builder;
}
