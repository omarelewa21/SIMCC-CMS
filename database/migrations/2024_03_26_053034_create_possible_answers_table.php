<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('possible_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competition_id');
            $table->unsignedBigInteger('level_id');
            $table->unsignedBigInteger('collection_id');
            $table->unsignedBigInteger('section_id');
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('answer_id');
            $table->string('answer_key');
            $table->json('possible_keys')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->timestamps();

            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('competition_id')->references('id')->on('competition')->onDelete('cascade');
            $table->foreign('collection_id')->references('id')->on('collection')->onDelete('cascade');
            $table->foreign('level_id')->references('id')->on('competition_levels')->onDelete('cascade');
            $table->foreign('section_id')->references('id')->on('collection_sections')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('answer_id')->references('id')->on('task_answers')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('possible_answers', function (Blueprint $table) {
            $table->dropForeign(['competition_id']);
            $table->dropForeign(['level_id']);
            $table->dropForeign(['task_id']);
            $table->dropForeign(['answer_id']);
            $table->dropForeign(['approved_by']);
        });

        Schema::dropIfExists('possible_answers');
    }
};
