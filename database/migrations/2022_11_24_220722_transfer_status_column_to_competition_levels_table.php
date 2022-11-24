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
            $table->set('computing_status', ['Not Started', 'In Progress', 'Finished'])->default('Not Started');
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
        });

        Schema::table('competition_marking_group', function (Blueprint $table) {
            $table->set('status', ['active', 'computed', 'computing'])->default('active');
        });
    }
};
