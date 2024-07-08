<?php
namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Trait Filter
 *
 * Tested with one level of relation only (e.g. 'relation.field')
 *
 * Todo - Test with multiple levels of relation (e.g. 'relation.relation.field')
 */
trait Filter
{
    public function scopeFilter($query, Request $request)
    {
        if(! property_exists($this, 'filterable')) return $query;

        foreach ($this->filterable as $queryParam => $searchField) {
            if(!$request->filled($queryParam)) continue;

            if($this->IsFieldToSearchARelation($searchField)) {
                $this->filterRelation($query, $request, $searchField, $queryParam);
                continue;
            }

            $this->doFilter($query, $searchField, $request->$queryParam);
        }
    }

    private function filterRelation($query, $request, $relation, $queryParam)
    {
        $eloquentRelation = Str::before($relation, '.');
        $fieldToSearch = Str::after($relation, '.');

        $query->whereHas($eloquentRelation, function ($query) use ($request, $fieldToSearch, $queryParam) {
            if($this->IsFieldToSearchARelation($fieldToSearch)) {
                $this->filterRelation($query, $request, $fieldToSearch, $queryParam);
                return;
            }

            $this->doFilter($query, $fieldToSearch, $request->$queryParam);
        });
    }

    private function IsFieldToSearchARelation($fieldToSearch)
    {
        return Str::contains($fieldToSearch, '.');
    }

    private function doFilter($query, $searchField, $value)
    {
        $table = $query->getModel()->getTable();
        $query->where("$table.$searchField", $value);
    }
}
