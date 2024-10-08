<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * The controller namespace for the application.
     *
     * When present, controller route declarations will automatically be prefixed with this namespace.
     *
     * @var string|null
     */
    protected $namespace = 'App\\Http\\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::prefix('api')
                ->middleware('api')
                ->namespace($this->namespace)
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->namespace($this->namespace)
                ->group(base_path('routes/web.php'));
        });

        Route::bind('task', function ($value) {
            return \App\Models\Tasks::findOrFail($value);
        });
        Route::bind('collection', function ($value) {
            return \App\Models\Collections::findOrFail($value);
        });
        Route::bind('round', function ($value) {
            return \App\Models\CompetitionRounds::findOrFail($value);
        });
        Route::bind('level', function ($value) {
            return \App\Models\CompetitionLevels::findOrFail($value);
        });
        Route::bind('group', function ($value) {
            return \App\Models\CompetitionMarkingGroup::findOrFail($value);
        });
        Route::bind('competition', function ($value) {
            return \App\Models\Competition::findOrFail($value);
        });
        Route::bind('participant', function ($value) {
            return \App\Models\Participants::where('index_no', $value)->firstOrFail();
        });
        Route::bind('country', function ($value) {
            return \App\Models\Countries::findOrFail($value);
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
