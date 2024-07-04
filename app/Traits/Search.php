<?php
namespace App\Traits;

trait Search
{
    public function scopeSearch($query, $search)
    {
        if(!isset($this->searchable) || empty($search)) return $query;

        $search = strtolower($search);

        $query->where(function($query) use ($search) {
            foreach($this->searchable as $field) {
                $query->orWhereRaw("LOWER($field) LIKE ?", ["%$search%"]);
            }
        });

        return $query;
    }
}
