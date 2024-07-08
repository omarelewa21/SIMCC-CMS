<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait Search
{
    public function scopeSearch($query, $searchKey)
    {
        if(!isset($this->searchable) || empty($searchKey)) return $query;

        $searchKey = strtolower($searchKey);

        $query->where(function($query) use ($searchKey) {
            foreach($this->searchable as $field) {
                if(Str::contains($field, '.')) {
                    $this->searchRelation($query, $field, $searchKey);
                    continue;
                }

                $query->orWhereRaw("LOWER($field) LIKE ?", ["%$searchKey%"]);
            }
        });

        return $query;
    }

    private function searchRelation($query, $field, $searchKey)
    {
        $relation = Str::before($field, '.');
        $field = Str::after($field, '.');

        $query->orWhereHas($relation, function($query) use ($field, $searchKey) {
            if(Str::contains($field, '.')) {
                $this->searchRelation($query, $field, $searchKey);
                return;
            }
            $query->whereRaw("LOWER($field) LIKE ?", ["%$searchKey%"]);
        });
    }
}
