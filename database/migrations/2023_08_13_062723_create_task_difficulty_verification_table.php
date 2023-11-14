<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::create('task_difficulty_verification', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competition_id');
            $table->unsignedBigInteger('round_id');
            $table->unsignedBigInteger('level_id');
            $table->unsignedBigInteger('verified_by_userid');
            $table->boolean('is_verified')->nullable();
            $table->timestamp('verified_at')->default(DB::raw('CURRENT_TIMESTAMP'));
            $table->index('competition_id');
            $table->foreign('competition_id')
                ->references('id')
                ->on('competition')
                ->onDelete('cascade');
            $table->index('round_id');
            $table->foreign('round_id')
                ->references('id')
                ->on('competition_rounds')
                ->onDelete('cascade');
            $table->index('level_id');
            $table->foreign('level_id')
                ->references('id')
                ->on('competition_levels')
                ->onDelete('cascade');
            $table->index('verified_by_userid');
            $table->foreign('verified_by_userid')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('task_difficulty_verification');
    }
};
