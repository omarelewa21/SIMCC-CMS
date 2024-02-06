<?php

use App\Models\Competition;
use App\Models\CompetitionParticipantsResults;
use App\Models\Participants;
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
            $table->decimal('points', $precision = 8, $scale = 2)->nullable()->change();
            $table->decimal('percentile', $precision = 5, $scale = 2)->nullable()->default(null)->change();
        });

        $indexesList = array("062231002962", "062231007547", "062231007507", "062231007530",
            "062231007531", "062231007532", "062231007533", "062231007534", "062231007535",
            "062231007536", "062231007540", "062231007541", "062231007542", "062231007543",
            "062231007544", "062231007545", "062231007525", "062231004821","062231005677",
            "062231006690", "062231007253", "055230004718");
        
        foreach ($indexesList as $index) {
            $competition = Competition::find(61);
            $participant = Participants::where('index_no', $index)->first();
            $levelId = $competition->levels()->whereJsonContains('grades', $participant->grade)->value('competition_levels.id');
            $groupId = $competition->groups()
                ->join('competition_marking_group_country as cmgc', 'competition_marking_group.id', 'cmgc.marking_group_id')
                ->where('cmgc.country_id', $participant->country_id)
                ->select('competition_marking_group.id')
                ->value('competition_marking_group.id');
            CompetitionParticipantsResults::create([
                'participant_index' => $index,
                'level_id'          => $levelId,
                'group_id'          => $groupId,
                'ref_award'         => 'CERTIFICATE OF PARTICIPATION',
                'award'             => 'CERTIFICATE OF PARTICIPATION',
            ]);
        }

        Participants::whereIn('index_no', $indexesList)->update(['status' => 'result computed']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('competition_participants_results', function (Blueprint $table) {
            $table->decimal('points', $precision = 8, $scale = 2)->change();
            $table->decimal('percentile', $precision = 5, $scale = 2)->default(0)->change();
        });

        $indexesList = array("062231002962", "062231007547", "062231007507", "062231007530",
            "062231007531", "062231007532", "062231007533", "062231007534", "062231007535",
            "062231007536", "062231007540", "062231007541", "062231007542", "062231007543",
            "062231007544", "062231007545", "062231007525", "062231004821","062231005677",
            "062231006690", "062231007253", "055230004718");
        CompetitionParticipantsResults::whereIn('participant_index', $indexesList)->delete();
        Participants::whereIn('index_no', $indexesList)->update(['status' => 'absent']);
    }
};
