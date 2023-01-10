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
        Schema::table('participant_answers', function (Blueprint $table) {
            $table->boolean('is_correct')->nullable();
        });

        Schema::table('competition_participants_results', function (Blueprint $table) {
            $table->json('report')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('participant_answers', function (Blueprint $table) {
            $table->dropColumn('is_correct');
        });
        Schema::table('competition_participants_results', function (Blueprint $table) {
            $table->dropColumn('report');
        });
    }
};
