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
        Schema::table('participant_reports', function ($table) {
            DB::statement("ALTER TABLE `participant_reports` MODIFY `status` ENUM('pending','in_progress','failed', 'completed','cancelled') NOT NULL DEFAULT 'pending'");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('participant_reports', function ($table) {
            DB::statement("ALTER TABLE `participant_reports` MODIFY `status` ENUM('pending', 'completed', 'failed', 'in_progress') NOT NULL DEFAULT 'pending'");
        });
    }
};
