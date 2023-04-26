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
        Schema::create('eliminated_cheating_participants', function (Blueprint $table) {
            if(env('APP_ENV') === 'local_') {
                $table->string('participant_index', 16)->collation('utf8mb4_unicode_ci')->primary();
            } else {
                $table->string('participant_index', 16)->collation('utf8mb4_0900_ai_ci')->primary();
            }
            $table->foreign('participant_index')->references('index_no')->on('participants');
            $table->string('reason')->nullable()->comment('Reason for elimination');
            $table->foreignId('created_by')->constrained('users');
            $table->date('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('eliminated_cheating_participants');
    }
};
