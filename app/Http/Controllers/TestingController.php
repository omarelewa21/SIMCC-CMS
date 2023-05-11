<?php

namespace App\Http\Controllers;

use App\Http\Controllers\api\CompetitionController;
use App\Http\Requests\ParticipantReportWithCertificateRequest;
use App\Exports\CheatersExport;
use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use App\Models\CompetitionLevels;
use App\Models\CompetitionMarkingGroup;
use App\Models\CompetitionParticipantsResults;
use PDF;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

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
        $pdf = PDF::loadView('performance-report', $data);
        return $pdf->download(sprintf("%s-report.pdf", $participantResult->participant->name));
    }


    public function setSchoolsToActive(Request $request)
    {
        $Ids = collect($request->all())->pluck('school_id')->unique()->toArray();
        // dd(School::whereIn('id', $Ids)->toSql());
        School::whereIn('id', $Ids)->update(['status' => 'active']);
        return response('all schools updates successfully', 200);
    }

    public function fixIndianParticipants()
    {
        try {
            $participants = Participants::where('index_no', 'like', '0912300%')->where('country_id', 108)
                ->whereNotNull('tuition_centre_id')->get();
            foreach($participants as $participant){
                $participant_answers = ParticipantsAnswer::where('participant_index', $participant->index_no)->get();
                $participantAnswersToInsert = $participant_answers->toArray();
                ParticipantsAnswer::where('participant_index', $participant->index_no)->delete();

                foreach($participant_answers as $participant_answer){
                    $participant_answer->participant_index = substr_replace($participant_answer->participant_index, "1", 5, 1);
                    $participant_answer->save();
                }
                $participant->index_no = substr_replace($participant->index_no, "1", 5, 1);
                $participant->save();

                ParticipantsAnswer::insert($participantAnswersToInsert);
            }
            return response()->json(['message' => 'success'], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
        
    }

    public function generateCheatersCSV(Competition $competition)
    {
        return Excel::download(new CheatersExport($competition), 'cheaters.xlsx');
    }
}
