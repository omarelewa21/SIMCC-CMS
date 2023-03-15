<?php

namespace App\Providers;

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
        Collection::macro('paginate', function (int $perPage = 10, int $page = 1) {
            return [
                'data'  => $this->slice(($page - 1) * $perPage, $perPage)->values(),
                'total' => $this->count(),
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($this->count() / $perPage),
                'first_page_url' => url()->current() . '?page=1',
                'last_page_url' => url()->current() . '?page=' . ceil($this->count() / $perPage),
                'next_page_url' => $page < ceil($this->count() / $perPage) ? url()->current() . '?page=' . ($page + 1) : null
            ];
        });
    }
}
