<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RecommendedDifficulty extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::create('recommended_difficulty',function ($table) {
        //     $table->bigIncrements('id');
        //     $table->morphs('recommended_difficulty');
        //     $table->string('difficulty',255);
        //     $table->TinyInteger('grade')->unsigned();
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
