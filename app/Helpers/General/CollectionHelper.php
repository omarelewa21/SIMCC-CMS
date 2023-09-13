<?php


namespace App\Helpers\General;


use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;


class CollectionHelper
{
    public static function paginate(Collection $results, $pageSize)
    {
        $page = Paginator::resolveCurrentPage('page');

        $total = $results->count();

        return self::paginator($results->forPage($page, $pageSize), $total, $pageSize, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);

    }

    /**
     * Create a new length-aware paginator instance.
     *
     * @param \Illuminate\Support\Collection $items
     * @param int $total
     * @param int $perPage
     * @param int $currentPage
     * @param array $options
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    protected static function paginator($items, $total, $perPage, $currentPage, $options)
    {
        return Container::getInstance()->makeWith(LengthAwarePaginator::class, compact(
            'items', 'total', 'perPage', 'currentPage', 'options'
        ));
    }

    static function searchCollection ($searchTerm, $collection, $availForSearch, $limit) {
        if(!empty($searchTerm)) {

            $userList = self::paginate(collect($collection)
                ->filter(function ($row) use ($searchTerm, $availForSearch) {

                    return collect($availForSearch)->map(
                        fn($item)=> $row[$item]
                    )->contains(
                        fn($item)=> is_string($item) ? str_contains(Str::lower($item), Str::lower(str_replace('%', ' ', $searchTerm))) : false
                    );

                }), $limit);
        } else {
            $userList = CollectionHelper::paginate($collection, $limit);
        }

        return $userList;
    }

    /**
     * Adjust filters array to be used in Collection::filterByRequest
     * 
     * @param array $filters
     */
    public static function adjustFilters(array &$filters)
    {
        foreach($filters as $index => $value){
            if(gettype($index) === 'integer'){
                $filters[$value] = $value;
                unset($filters[$index]);
            }
        }
    }

    /**
     * Filter collection by request
     * 
     * @param \Illuminate\Support\Collection $collection
     * @param array $filters
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Support\Collection
     */
    public static function filterColletion(Collection $collection, array $filters, Request $request)
    {
        foreach($filters as $filter => $column){
            if($request->has($filter)){
                $collection = $collection->where($column, $request->get($filter));
            }
        }
        return $collection;
    }

    /**
     * Search in collection
     * 
     * @param string $searchTerm
     * @param \Illuminate\Support\Collection $collection
     * @param array $searchAttributes
     * @return \Illuminate\Support\Collection
     */
    public static function searchInCollection(string $searchTerm, Collection $collection, $searchAttributes = [])
    {
        return $collection->filter(function ($item) use ($searchTerm, $searchAttributes) {
            foreach ($searchAttributes as $attribute) {
                if(is_array($item[$attribute])){
                    $value = Arr::flatten(Arr::get($item, $attribute));
                } else {
                    $value = $item[$attribute];
                }

                if (is_array($value)) {
                    foreach ($value as $nestedItem) {
                        if (str_contains(strtolower($nestedItem), strtolower($searchTerm))) {
                            return true;
                        }
                    }
                } else {
                    if (str_contains(strtolower($value), strtolower($searchTerm))) {
                        return true;
                    }
                }
            }
            return false;
        });
    }

}
