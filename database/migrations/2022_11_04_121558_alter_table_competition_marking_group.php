<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTableCompetitionMarkingGroup extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('competition_marking_participants');
        Schema::dropIfExists('competition_marking_group');

        Schema::create('competition_marking_group', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->set('status', ['active', 'computed', 'computing'])->default('active');
            $table->foreignId('created_by_userid')->nullable()->constrained('users');
            $table->foreignId('last_modified_userid')->nullable()->constrained('users');
            $table->timestamps();
        });

        Schema::create('competition_marking_group_country', function (Blueprint $table) {
            $table->unsignedBigInteger('marking_group_id')->unique();
            $table->foreign('marking_group_id')->references('id')->on('competition_marking_group');
            $table->unsignedSmallInteger('country_id');
            $table->foreign('country_id')->references('id')->on('all_countries');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('competition_marking_groups');
        Schema::dropIfExists('competition_marking_group_country');
    }
}
