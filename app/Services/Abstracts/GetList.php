<?php

namespace App\Services\Abstracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;

abstract class GetList
{
    protected $baseQueryForFilters;

    public function __construct(protected Request $request)
    {
        $this->baseQueryForFilters = $this->getBaseQueryForFilters();
    }

    protected function getBaseQueryForFilters(): Builder
    {
        return $this->getModel()::distinct()->doFilter($this->request);
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

    protected function getFilterOptionsQuery(): Builder
    {
        if(!property_exists($this->getModel(), 'filterable')) {
            return $this->getModel()::whereRaw('1 = 0');
        }

        $filterKey = $this->request->get('get_filter');
        $filterables = $this->filterables();

        return $filterables->has($filterKey)
            ? $filterables->get($filterKey)()
            : $this->getModel()::whereRaw('1 = 0');
    }

    protected function filterables(): SupportCollection
    {
        return collect($this->getInstance()->filterable)
            ->except('id')
            ->map(fn($value, $key) => fn() => $this->{Str::camel("get_$key")}());
    }

    protected function returnTableData(): LengthAwarePaginator|Collection
    {
        return $this->getRespectiveUserModelQuery()
            ->with($this->getWithRelations())
            ->doFilter($this->request)
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

    protected function getStatus(): Builder
    {
        return (clone $this->baseQueryForFilters)->select("status as filter_id","status as filter_name");
    }

    protected function getTable(): string
    {
        return $this->getInstance()->getTable();
    }

    protected function getInstance(): object
    {
        return new ($this->getModel());
    }

    protected function getRespectiveUserModelQuery(): Builder
    {
        return $this->getModel()::query();
    }

    protected abstract function getModel(): string;
    protected abstract function getWithRelations(): array;
}
