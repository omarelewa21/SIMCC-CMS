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
        Schema::create('cheating_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('competition');
            if(env('APP_ENV') === 'local_') {
                $table->string('participant_index', 16)->collation('utf8mb4_unicode_ci');
                $table->string('cheating_with_participant_index', 16)->collation('utf8mb4_unicode_ci');
            } else {
                $table->string('participant_index', 16)->collation('utf8mb4_0900_ai_ci');
                $table->string('cheating_with_participant_index', 16)->collation('utf8mb4_0900_ai_ci');
            }
            $table->foreign('participant_index')->references('index_no')->on('participants');
            $table->foreign('cheating_with_participant_index')->references('index_no')->on('participants');
            $table->unsignedInteger('group_id');
            $table->unsignedTinyInteger('number_of_cheating_questions');
            $table->decimal('cheating_percentage', 5, 2);
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
        Schema::dropIfExists('cheating_participants');
    }
};
