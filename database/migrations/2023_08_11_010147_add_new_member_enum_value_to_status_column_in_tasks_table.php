<?php

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
        DB::statement("ALTER TABLE `tasks` CHANGE `status` `status` ENUM('Active','Pending Moderation','Deleted','Rejected','Verified') DEFAULT 'Pending Moderation';");
        DB::table('tasks')->whereNull('status')->OrWhere('status', '')
            ->update(['status' => 'Pending Moderation']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `tasks` CHANGE `status` `status` ENUM('Active','Pending Moderation','Deleted','Rejected') DEFAULT 'Pending Moderation';");
        DB::table('tasks')->whereNull('status')->OrWhere('status', '')
            ->update(['status' => 'Pending Moderation']);
    }
};
