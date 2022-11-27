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
        Schema::table('competition_levels', function (Blueprint $table) {
            $table->set('computing_status', ['Not Started', 'In Progress', 'Finished', 'Bug Detected'])->default('Not Started');
            $table->text('compute_error_message')->nullable();
            $table->unsignedDecimal('compute_progress_percentage', $precision = 3, $scale = 0)->nullable();
        });

        Schema::table('competition_marking_group', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('competition_levels', function (Blueprint $table) {
            $table->dropColumn('computing_status');
            $table->dropColumn('compute_error_message');
            $table->dropColumn('compute_progress_percentage');
        });

        Schema::table('competition_marking_group', function (Blueprint $table) {
            $table->set('status', ['active', 'computed', 'computing'])->default('active');
        });
    }
};
