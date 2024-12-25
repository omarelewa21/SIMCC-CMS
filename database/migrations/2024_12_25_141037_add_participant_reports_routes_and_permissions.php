<?php

use App\Models\Roles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $listReportsRouteId = DB::table('routes')->insertGetId(
            ['route_name' => 'participant.reports.list']
        );
        $generateReportsRouteId = DB::table('routes')->insertGetId(
            ['route_name' => 'participant.reports.generate']
        );
        $downloadReportsRouteId = DB::table('routes')->insertGetId(
            ['route_name' => 'participant.reports.download']
        );
        $deleteReportsRouteId = DB::table('routes')->insertGetId(
            ['route_name' => 'participant.reports.delete']
        );
        $cancelReportsRouteId = DB::table('routes')->insertGetId(
            ['route_name' => 'participant.reports.cancel']
        );

        DB::table('permissions')->insert([
            ['route_id' => $listReportsRouteId, 'role_id' => Roles::COUNTRY_PARTNER_ID],
            ['route_id' => $generateReportsRouteId, 'role_id' => Roles::COUNTRY_PARTNER_ID],
            ['route_id' => $downloadReportsRouteId, 'role_id' => Roles::COUNTRY_PARTNER_ID],
            ['route_id' => $deleteReportsRouteId, 'role_id' => Roles::COUNTRY_PARTNER_ID],
            ['route_id' => $cancelReportsRouteId, 'role_id' => Roles::COUNTRY_PARTNER_ID],

            ['route_id' => $listReportsRouteId, 'role_id' => Roles::COUNTRY_PARTNER_ASSISTANT_ID],
            ['route_id' => $generateReportsRouteId, 'role_id' => Roles::COUNTRY_PARTNER_ASSISTANT_ID],
            ['route_id' => $downloadReportsRouteId, 'role_id' => Roles::COUNTRY_PARTNER_ASSISTANT_ID],
            ['route_id' => $deleteReportsRouteId, 'role_id' => Roles::COUNTRY_PARTNER_ASSISTANT_ID],
            ['route_id' => $cancelReportsRouteId, 'role_id' => Roles::COUNTRY_PARTNER_ASSISTANT_ID],
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
            ->whereIn('routes.route_name', [
                'participant.reports.list',
                'participant.reports.generate',
                'participant.reports.download',
                'participant.reports.delete',
                'participant.reports.cancel'
            ])
            ->delete();

        DB::table('routes')
            ->whereIn('route_name', [
                'participant.reports.list',
                'participant.reports.generate',
                'participant.reports.download',
                'participant.reports.delete',
                'participant.reports.cancel'
            ])
            ->delete();
    }
};
