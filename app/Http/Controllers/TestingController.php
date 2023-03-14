<?php

namespace App\Http\Controllers;

use App\Http\Requests\ParticipantReportWithCertificateRequest;
use App\Models\Competition;
use App\Models\CompetitionLevels;
use App\Models\CompetitionMarkingGroup;
use App\Models\CompetitionParticipantsResults;
use PDF;
use Illuminate\Support\Facades\DB;

class TestingController extends Controller
{
    public function getNumberOfParticipantsByLevelId(CompetitionLevels $level)
    {
        return $level->participantsAnswersUploaded()->count();
    }

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

    public function testPDF(ParticipantReportWithCertificateRequest $request)
    {
        $participantResult = CompetitionParticipantsResults::where('participant_index', $request->index_no)
                ->with('participant')->firstOrFail()->makeVisible('report');
        $report = $participantResult->report;
        $data = [
            'general_data'                  => $report['general_data'],
            'performance_by_questions'      => $report['performance_by_questions'],
            'performance_by_topics'         => $report['performance_by_topics'],
            'grade_performance_analysis'    => $report['grade_performance_analysis'],
            'analysis_by_questions'         => $report['analysis_by_questions']
        ];
        $pdf = PDF::loadView('testPdf', $data);
        return $pdf->download(sprintf("%s-report.pdf", $participantResult->participant->name));
    }
}
