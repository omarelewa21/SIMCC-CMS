<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class RolePermissions
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */

    public function handle(Request $request, Closure $next)
    {
        return $this->isUserAuthorizedForThisRoute()
            ? $next($request)
            : response()->json([
                "status"    => 405,
                "message"   => "Unauthorized to perform this action"
            ], 405);
    }

    /**
     * Check if user is authorized to perform action
     * 
     * @return bool
     */
    private function isAuthorized(): bool
    {
        return DB::table("permissions")
            ->join("routes", "permissions.route_id", "routes.id")
            ->where("permissions.role_id", auth()->user()->role_id)
            ->where("routes.route_name", Route::currentRouteName())
            ->exists();
    }

    /**
     * Check if user is authorized to perform action - old version
     * 
     * @return bool
     */
    private function __isAuthorized(): bool
    {
        return DB::table("permission_lists")
            ->leftJoin("roles_permission", "permission_lists.id", "roles_permission.permission_list_id")
            ->leftJoin("permission_dict as a", "permission_lists.permission_name_id", "a.id")
            ->leftJoin("permission_dict as b", "permission_lists.permission_action_id", "b.id")
            ->where("roles_permission.roles_id", "=", auth()->user()->role_id)
            ->whereRaw("CONCAT(a.name, '.', b.name) = ?", [Route::currentRouteName()])
            ->exists();
    }

    private function isUserAuthorizedForThisRoute(): bool
    {
        return auth()->user()->hasRole(['Admin', 'Super Admin'])
            || $this->__isAuthorized()
            || $this->isAuthorized();
    }
}
