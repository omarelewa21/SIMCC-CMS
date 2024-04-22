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
            // Drop foreign key constraints
            DB::statement('ALTER TABLE `possible_similar_answers` DROP FOREIGN KEY `possible_answers_answer_id_foreign`;');

            // Drop columns
            $table->dropColumn('answer_id');
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
            // Add columns back
            $table->unsignedBigInteger('answer_id')->nullable();

            // Add foreign key constraints
            $table->foreign('answer_id', 'answer_id')->references('id')->on('task_answers')->onDelete('cascade');
        });
    }
};
