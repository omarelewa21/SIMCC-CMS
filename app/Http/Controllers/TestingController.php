<?php

namespace App\Http\Controllers;

use App\Http\Controllers\api\CompetitionController;
use App\Models\Competition;
use App\Models\CompetitionLevels;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class TestingController extends Controller
{
    public function getNumberOfParticipantsByLevelId(CompetitionLevels $level)
    {
        return $level->participantsAnswersUploaded()->count();
    }

    public function testCompetitionReportData(Competition $competition)
    {
        // return collect((new CompetitionController())->old_report($competition))->sortBy('index_no')->toArray();
        return collect((new CompetitionController())->report($competition, Request::create('/', 'GET', ['mode' => 'csv'])))
            ->map(fn($arr)=> Arr::except($arr, ['country_id', 'school_id']))
            ->sortBy('index_no')->toArray();
    }
}
