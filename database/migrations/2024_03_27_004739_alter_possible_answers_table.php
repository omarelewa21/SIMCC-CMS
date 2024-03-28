<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterPossibleAnswersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('possible_answers', function (Blueprint $table) {
            $table->dropForeign(['competition_id']);
            $table->dropForeign(['level_id']);
            $table->dropForeign(['collection_id']);
            $table->dropForeign(['section_id']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['competition_id', 'level_id', 'collection_id', 'section_id']);
        });

        Schema::rename('possible_answers', 'possible_similar_answers');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::rename('possible_similar_answers', 'possible_answers');
        Schema::table('possible_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('competition_id')->nullable();
            $table->unsignedBigInteger('level_id')->nullable();
            $table->unsignedBigInteger('collection_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
        });
    }
}
