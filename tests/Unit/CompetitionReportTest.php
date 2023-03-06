<?php

namespace Tests\Unit;

use App\Http\Controllers\api\CompetitionController;
use App\Models\Competition;
use App\Models\User;
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
        $OmarData = (new CompetitionController())->omar_report($competition)->sortBy('index_no')->toArray();
        $AdrenData = collect((new CompetitionController())->report($competition))->sortBy('index_no')->toArray();

        $this->assertEqualsCanonicalizing($AdrenData, $OmarData);
    }
}
