<?php

use App\Models\Roles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $schoolApproveRouteId = DB::table('routes')->insertGetId(
            ['route_name' => 'school.approve']
        );
        $schoolRejectRouteId = DB::table('routes')->insertGetId(
            ['route_name' => 'school.reject']
        );
        DB::table('permissions')->insert([
            ['route_id' => $schoolApproveRouteId, 'role_id' => Roles::COUNTRY_PARTNER_ID],
            ['route_id' => $schoolRejectRouteId, 'role_id' => Roles::COUNTRY_PARTNER_ID],
            ['route_id' => $schoolApproveRouteId, 'role_id' => Roles::COUNTRY_PARTNER_ASSISTANT_ID],
            ['route_id' => $schoolRejectRouteId, 'role_id' => Roles::COUNTRY_PARTNER_ASSISTANT_ID]
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('permissions')->join('routes', 'permissions.route_id', 'routes.id')
        ->where('routes.route_name', 'school.approve')
        ->orWhere('routes.route_name', 'school.reject')
        ->delete();

        DB::table('routes')
        ->where('route_name', 'school.approve')
        ->orWhere('route_name', 'school.reject')
        ->delete();
    }
};
