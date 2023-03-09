<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use App\Models\CompetitionLevels;
use App\Models\CompetitionMarkingGroup;
use Illuminate\Support\Facades\DB;

class TestingController extends Controller
{
    public function getNumberOfParticipantsByLevelId(CompetitionLevels $level)
    {
        return $level->participantsAnswersUploaded()->count();
    }

    /**
     * 
     */
    public function storeRemainingGroupCountriesForCompetitionId(int $competitionId)
    {
        $competition = Competition::findOrFail($competitionId);
        $countries = $competition->participants()
                    ->pluck('participants.country_id')->unique()->toArray();
        $markingGroup = CompetitionMarkingGroup::where('competition_id', $competitionId)->firstOrFail();

        foreach($countries as $country_id){
            DB::table('competition_marking_group_country')->updateOrInsert(
                ['marking_group_id' => $markingGroup->id, 'country_id' => $country_id],
                ['created_at' => now(), 'updated_at' => now()]
              );
        }

        return response('Success', 200);
    }
}
