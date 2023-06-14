<?php

use App\Models\CompetitionParticipantsResults;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
            CompetitionParticipantsResults::where('award', 'PERFECT SCORER')->update(
                [
                    'award' => 'PERFECT SCORE',
                    'ref_award' => 'PERFECT SCORE',
                ]
            );

            CompetitionParticipantsResults::where('global_rank', 'like', 'PERFECT SCORER%')
                ->update(
                    [
                        'global_rank' => Str::replaceFirst('PERFECT SCORER', 'PERFECT SCORE', 'PERFECT SCORER')
                    ]
                );
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
            CompetitionParticipantsResults::where('award', 'PERFECT SCORE')->update(
                [
                    'award' => 'PERFECT SCORER',
                    'ref_award' => 'PERFECT SCORER'
                ]
            );

            CompetitionParticipantsResults::where('global_rank', 'like', 'PERFECT SCORE%')
                ->update(
                    [
                        'global_rank' => Str::replaceFirst('PERFECT SCORE', 'PERFECT SCORER', 'PERFECT SCORE')
                    ]
                );
        });
    }
};
