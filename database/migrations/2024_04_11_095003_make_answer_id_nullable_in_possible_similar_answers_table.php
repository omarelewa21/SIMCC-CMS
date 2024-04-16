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
        Schema::table('possible_similar_answers', function (Blueprint $table) {
            $table->bigInteger('answer_id')->unsigned()->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('possible_similar_answers', function (Blueprint $table) {
            $table->bigInteger('answer_id')->unsigned()->nullable(false)->change();
        });
    }
};
