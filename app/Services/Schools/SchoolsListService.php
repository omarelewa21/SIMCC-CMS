<?php

namespace App\Services\Schools;

use App\Abstracts\GetList;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

class SchoolsListService extends GetList
{
    protected function getModel(): string
    {
        return School::class;
    }

    protected function returnTableData(): LengthAwarePaginator|EloquentCollection
    {
        return $this->getRespectiveUserModelQuery()
            ->with($this->getWithRelations())
            ->withCount('participants')
            ->doFilter($this->request)
            ->search($this->request->search ?? '')
            ->orderBy("{$this->getTable()}.updated_at", 'desc')
            ->when(
                $this->request->has('private'),
                fn($query) => $query->where('schools.private', $this->request->private)
            )
            ->when(
                $this->request->mode === 'csv',
                fn($query) => $this->getCSVQuery($query)->get(),
                fn($query) => $query->paginate($this->request->limits ?? defaultLimit())
            );
    }

    protected function getWithRelations(): array
    {
        return [
            'reject_reason:reject_id,reason,created_at,created_by_userid',
            'reject_reason.user:id,username',
            'reject_reason.role:roles.name',
            'teachers',
            'country:id,display_name as name',
            'approved_by:id,name'
        ];
    }

    protected function getRespectiveUserModelQuery(): Builder
    {
        $user = auth()->user();

        if($user->hasRole(['Country Partner', 'Country Partner Assistant'])) {
            return School::where("country_id", $user->country_id);
        }

        if($user->hasRole(['School Manager', 'Teacher'])) {
            return School::whereId($user->school_id);
        }

        return School::query();
    }

    protected function getCSVQuery(Builder $query): Builder
    {
        return $query->join('all_countries', 'all_countries.id', 'schools.country_id')
            ->selectRaw(
                "CONCAT('\"',schools.name,'\"') as name,
                CONCAT('\"',all_countries.display_name,'\"') as country,
                schools.status,
                schools.email,
                CONCAT('\"',schools.address,'\"') as address,
                CONCAT('\"',schools.postal,'\"') as postal,
                CONCAT('\"',schools.province,'\"') as province,
                schools.phone"
            );
    }

    protected function filterables(): Collection
    {
        return collect(array_merge($this->getInstance()->filterable, ['private' => 'private']))
            ->except('id')
            ->map(fn($value, $key) => fn() => $this->{Str::camel("get_$key")}());
    }

    protected function getPrivate(): Builder
    {
        return (clone $this->baseQueryForFilters)
            ->whereNotNull('private')
            ->selectRaw("
                private as filter_id,
                CASE WHEN private = 1 THEN 'Private' ELSE 'School' END as filter_name
            ");
    }

    protected function getCountry(): Builder
    {
        return (clone $this->baseQueryForFilters)
            ->join('all_countries', 'all_countries.id', '=', 'schools.country_id')
            ->select('all_countries.id as filter_id', 'all_countries.display_name as filter_name');
    }
}
