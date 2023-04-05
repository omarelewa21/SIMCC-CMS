<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
        $rolePermissions = DB::table("permission_lists")
            ->leftJoin("roles_permission", function($join){
                $join->on("permission_lists.id", "=", "roles_permission.permission_list_id");
            })
            ->leftJoin("permission_dict as a", function($join){
                $join->on("permission_lists.permission_name_id", "=", "a.id");
            })
            ->leftJoin("permission_dict as b", function($join){
                $join->on("permission_lists.permission_action_id", "=", "b.id");
            })
            ->select(DB::raw('CONCAT(a.name, ".", b.name ) AS "permission"'))
            ->where("roles_permission.roles_id", "=", auth()->user()->role_id)
            ->pluck('permission')
            ->toArray();

        $bypassRoute = ['info.countryList','info.roles','info.languages','info.competitionlist'];

        if(auth()->user()->role_id > 1 && !in_array(Route::currentRouteName(),$bypassRoute))
        {
            if(!in_array(Route::currentRouteName(),$rolePermissions)) {
                return response()->json([
                    "status" => 405 ,"message" => "Unauthorized to perform this action " . Route::currentRouteName()
                ], 405);
            }
        }

        return $next($request);
    }
}
