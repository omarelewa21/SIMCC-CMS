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
        DB::statement("ALTER TABLE `difficulty_groups` CHANGE `status` `status` ENUM('active','deleted','verified') NOT NULL DEFAULT 'active';");
        DB::table('difficulty_groups')->whereNull('status')->OrWhere('status', '')
            ->update(['status' => 'active']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `difficulty_groups` CHANGE `status` `status` ENUM('active','deleted') NOT NULL DEFAULT 'active';");
        DB::table('difficulty_groups')->whereNull('status')->OrWhere('status', '')
            ->update(['status' => 'active']);
    }
};
