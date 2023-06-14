<?php

use App\Models\CompetitionParticipantsResults;
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
        Schema::table('competition_participants_results', function (Blueprint $table) {
            CompetitionParticipantsResults::where('award', 'PERFECT SCORER')->update(['award' => 'PERFECT SCORE']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('competition_participants_results', function (Blueprint $table) {
            CompetitionParticipantsResults::where('award', 'PERFECT SCORE')->update(['award' => 'PERFECT SCORER']);
        });
    }
};
