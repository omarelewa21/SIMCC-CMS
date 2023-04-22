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
        Schema::create('competition_cheat_compute_status', function (Blueprint $table) {
            $table->foreignId('competition_id')->constrained('competition');
            $table->enum('status', ['Not Started', 'In Progress', 'Completed', 'Bug Detected'])->default('Not Started');
            $table->decimal('cheating_percentage', 3, 0);
            $table->string('compute_error_message')->nullable();
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
