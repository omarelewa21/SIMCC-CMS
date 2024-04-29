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
        Schema::create('integrity_cases', function (Blueprint $table) {
            $table->id();
            if(env('APP_ENV') === 'local_') {
                $table->string('participant_index', 16)->collation('utf8mb4_unicode_ci');
            } else {
                $table->string('participant_index', 16)->collation('utf8mb4_0900_ai_ci');
            }
            $table->foreign('participant_index')->references('index_no')->on('participants');
            $table->string('reason')->nullable()->comment('Reason for elimination');
            $table->enum('mode', ['custom','system'])->nullable()->comment('Mode of elimination (custom or system)');
            $table->foreignId('created_by')->constrained('users');
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
        Schema::dropIfExists('integrity_cases');
    }
};
