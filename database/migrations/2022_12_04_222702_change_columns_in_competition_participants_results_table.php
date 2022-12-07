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
        Schema::table('competition_participants_results', function (Blueprint $table) {
            $table->unsignedSmallInteger('school_rank')->nullable()->change();
            $table->unsignedSmallInteger('country_rank')->nullable()->change();
            $table->unsignedSmallInteger('group_rank')->nullable()->change();
            $table->string('ref_award', 64)->nullable()->change();
            $table->string('award', 64)->nullable()->change();
            $table->unsignedSmallInteger('global_rank')->nullable()->change();
            // $table->unsignedSmallInteger('group_id')->nullable();
            // $table->unsignedSmallInteger('country_id')->nullable();
            // $table->unsignedSmallInteger('school_id')->nullable();
            $table->dropColumn('all_participants');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('competition_participants_results', function (Blueprint $table) {
            $table->unsignedSmallInteger('school_rank')->change();
            $table->unsignedSmallInteger('country_rank')->change();
            $table->unsignedSmallInteger('group_rank')->change();
            $table->string('ref_award', 64)->change();
            $table->string('award', 64)->change();
            $table->string('global_rank', 32)->change();
            $table->unsignedSmallInteger('all_participants');
            // $table->dropColumn('group_id');
            // $table->dropColumn('country_id');
            // $table->dropColumn('school_id');
        });
    }
};
