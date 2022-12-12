<?php


namespace App\Helpers\General;


use Illuminate\Container\Container;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

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
                        fn($item) => $row[$item]
                    )->contains(
                        fn($item) => str_contains($item, $searchTerm)
                    );

                }), $limit);
        } else {
            $userList = CollectionHelper::paginate($collection, $limit);
        }

        return $userList;
    }
}
