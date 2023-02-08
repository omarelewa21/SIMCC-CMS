<?php

namespace App\Http\Controllers;

use App\Models\CompetitionLevels;

class TestingController extends Controller
{
    public function getNumberOfParticipantsByLevelId(CompetitionLevels $level)
    {
        return $level->participantsAnswersUploaded()->count();
    }
}
