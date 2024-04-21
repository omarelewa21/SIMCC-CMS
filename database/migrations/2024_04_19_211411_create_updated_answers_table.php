<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('updated_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('level_id');
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('answer_id');
            $table->string('participant_index');
            $table->text('old_answer', 255);
            $table->text('new_answer', 255);
            $table->text('reason', 255)->nullable();
            $table->unsignedBigInteger('updated_by');
            $table->timestamps();

            $table->foreign('level_id')->references('id')->on('competition_levels')->onDelete('cascade');
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('answer_id')->references('id')->on('participant_answers')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('updated_answers');
    }
};
