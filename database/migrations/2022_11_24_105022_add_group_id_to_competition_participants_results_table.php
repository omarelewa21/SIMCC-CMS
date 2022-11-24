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
        Schema::dropIfExists('competition_participants_results');

        Schema::create('competition_participants_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('competition_marking_group');
            $table->string('participant_index', 16);
            $table->foreignId('ref_award_id')->constrained('competition_rounds_awards');
            $table->foreignId('award_id')->constrained('competition_rounds_awards');
            $table->decimal('points', $precision = 8, $scale = 2);
            $table->unsignedSmallInteger('school_rank');
            $table->unsignedSmallInteger('country_rank');
            $table->unsignedSmallInteger('group_rank');
            $table->string('global_rank', 32);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('competition_participants_results');
    }
};
