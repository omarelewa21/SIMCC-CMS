<?php

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
        Schema::table('competition_cheat_compute_status', function (Blueprint $table) {
            $table->timestamps();
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedMediumInteger('total_cases_count')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('competition_cheat_compute_status', function (Blueprint $table) {
            $table->dropForeign(['run_by']);
            $table->dropColumn('run_by');
            $table->dropColumn('total_cases_count');
            $table->dropTimestamps();
        });
    }
};
