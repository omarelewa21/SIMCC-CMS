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
        Schema::dropIfExists('competition_cheat_compute_status');
    
        Schema::create('competition_cheat_compute_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('competition');
            $table->enum('status', ['In Progress', 'Completed', 'Failed'])->default('In Progress');
            $table->decimal('progress_percentage', 3, 0);
            $table->string('compute_error_message')->nullable();
            $table->decimal('cheating_percentage', 3, 0)->default(85);
            $table->unsignedTinyInteger('number_of_same_incorrect_answers')->default(5);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('competition_cheat_compute_status');
    }
};
