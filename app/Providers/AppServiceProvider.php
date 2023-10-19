<?php

namespace App\Providers;

use App\Helpers\General\CollectionHelper;
use App\Models\School;
use App\Observers\SchoolObserver;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Collection::macro('filterByRequest', function (Request $request, $filters = [], $searchAttributes = []) {
            $collection = $this;
            if($request->hasAny($filters)){
                CollectionHelper::adjustFilters($filters);
                $collection = CollectionHelper::filterColletion($collection, $filters, $request);
            }

            if($request->has('search')){
                $collection = CollectionHelper::searchInCollection($request->search, $collection, $searchAttributes);
            }
            return $collection;
        });

        Collection::macro('paginate', function ($perPage = 10, $page = 1, $options = []) {
            $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);
            $results = $this->forPage($page, $perPage)->values();
            $options += [
                'path' => Paginator::resolveCurrentPath(),
                'pageName' => 'page',
            ];
            return new LengthAwarePaginator($results, $this->count(), $perPage, $page, $options);
        });

        School::observe(SchoolObserver::class);

    }
}
