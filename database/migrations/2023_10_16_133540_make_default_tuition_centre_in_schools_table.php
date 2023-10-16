<?php

use App\Models\School;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::table('schools', function (Blueprint $table) {
            $table->boolean('is_system_school')->default(false);
        });

        DB::table('schools')->updateOrInsert(
            ['id' => '2'],
            [
                'name'  => strtoupper('Default System Tuition Centre'),
                'is_system_school' => true,
                'status' => 'active',
                'country_id' => 202,
                'private'   => true,
                'created_by_userid' => 2,
                'approved_by_userid' => 2
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {   
        Schema::table('schools', function (Blueprint $table) {
            $table->dropColumn('is_system_school');
        });
    }
};
