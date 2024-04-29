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
        DB::statement("ALTER TABLE `integrity_cases` CHANGE `mode` `mode` ENUM('system','map','custom') DEFAULT NULL COMMENT 'Mode of elimination (custom or system or map)';");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('integrity_cases', function (Blueprint $table) {
            DB::statement("ALTER TABLE `integrity_cases` CHANGE `mode` `mode` ENUM('system','custom') DEFAULT NULL COMMENT 'Mode of elimination (custom or system)';");
        });
    }
};
