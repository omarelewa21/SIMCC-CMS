<?php

namespace App\Abstracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection;
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

    protected abstract function getModel(): string;
    protected abstract function getFilterOptionsQuery(): Builder;
    protected abstract function returnTableData(): LengthAwarePaginator;
}
