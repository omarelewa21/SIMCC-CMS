<?php

namespace Tests\Unit;

use App\Http\Controllers\api\CompetitionController;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Tests\TestCase;

class CompetitionReportTest extends TestCase
{
    CONST COMPETITION_ID = 49;

    /**
     * See if Adren's report is identical to Omar's report
     *
     * @return void
     */
    public function testOmarAndAdrenReportIdenticality()
    {
        $competition = Competition::find(self::COMPETITION_ID);
        $OmarData = collect((new CompetitionController())->report($competition, Request::create('/', 'GET', ['mode' => 'csv'])))
            ->map(fn($arr)=> Arr::except($arr, ['country_id', 'school_id']))
            ->sortBy('index_no')->toArray();
        $AdrenData = collect((new CompetitionController())->old_report($competition))->sortBy('index_no')->toArray();

        $this->assertEqualsCanonicalizing($AdrenData, $OmarData);
    }
}
