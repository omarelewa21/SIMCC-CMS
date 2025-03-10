<?php

namespace App\Http\Controllers;

use App\Exports\CheatersExport;
use App\Http\Controllers\Api\ParticipantAnswersController;
use App\Http\Requests\Participant\AnswerReportRequest;
use App\Models\Competition;
use App\Models\CompetitionLevels;
use App\Models\CompetitionMarkingGroup;
use App\Models\CompetitionParticipantsResults;
use App\Models\Countries;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
use App\Models\School;
use App\Services\ComputeLevelGroupService;
use App\Services\GradeService;
use App\Services\ParticipantReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

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

    public function fixGlobalRank(Competition $competition)
    {
        $levelIds = $competition->levels()->pluck('competition_levels.id');
        CompetitionParticipantsResults::whereIn('level_id', $levelIds)
            ->chunkById(1000, function ($results) {
                foreach ($results as $result) {
                    $globalRankText = trim(preg_replace('/[0-9]/', '', $result->global_rank));
                    if($globalRankText != $result->award) {
                        $globalRankNumber = preg_replace('/[^0-9]/', '', $result->global_rank);
                        $result->update([
                            'global_rank' => "$result->award $globalRankNumber"
                        ]);
                    }
                }
            });

        return response()->json([
            'message' => 'Global rank fixed'
        ]);
    }

    public function getWrongGlobalNumberCount(Competition $competition)
    {
        $levelIds = $competition->levels()->pluck('competition_levels.id');
        $count = 0;
        $data = [];
        CompetitionParticipantsResults::whereIn('level_id', $levelIds)
            ->chunkById(1000, function ($results) use(&$count, &$data) {
                foreach ($results as $result) {
                    $globalRankText = trim(preg_replace('/[0-9]/', '', $result->global_rank));
                    if ($globalRankText != $result->award) {
                        $count++;
                        $data[] = [
                            'id'    => $result->id,
                            'award' => $result->award,
                            'points' => $result->points,
                            'global_rank' => $result->global_rank
                        ];
                    }
                }
            });

        return response()->json([
            'message' => 'Wrong global rank count',
            'count' => $count,
            'data' => $data
        ]);
    }

    public function answerReport(Competition $competition)
    {
        $grades = $competition->levels()->pluck('grades')
            ->flatten()->unique()->sort()->values()->toArray();

        $grades = GradeService::getAvailableCorrespondingGradesFromList($grades);

        $countryIds = $competition->participants()->has('answers')
            ->select('participants.country_id')
            ->distinct()->pluck('participants.country_id')->toArray();

        $countries = Countries::whereIn('id', $countryIds)
            ->select('id', 'display_name as name')->get();

        return view('testing.answer-report', compact('competition', 'grades', 'countries'));
    }

    public function answerReportPost(Competition $competition, AnswerReportRequest $request)
    {
        if($request->method() == 'POST') {
            return (new ParticipantAnswersController())->answerReport($competition, $request);
        }
    }

    public function testGlobalRank($levelId)
    {
        $participantResults = CompetitionParticipantsResults::where('level_id', $levelId)
            ->orderBy('points', 'DESC')
            ->get()
            ->groupBy('award');

        foreach($participantResults as $award => $results) {
            foreach($results as $index => $participantResult){
                if($index === 0){
                    $participantResult->setAttribute('global_rank', sprintf("%s %s", $award, $index+1));
                } elseif ($participantResult->points === $results[$index-1]->points){
                    $globalRankNumber = preg_replace('/[^0-9]/', '', $results[$index-1]->global_rank);
                    $participantResult->setAttribute('global_rank', sprintf("%s %s", $award, $globalRankNumber));
                } else {
                    $participantResult->setAttribute('global_rank', sprintf("%s %s", $award, $index+1));
                }
                $participantResult->save();
            }
        }

        $this->updateComputeProgressPercentage(80);
    }

    public function testAwardAndPercentile($level, $group)
    {
        (new ComputeLevelGroupService($level, $group))->setParticipantsAwards();
    }

    public function report(Participants $participant)
    {
        $participantResult = $participant->result->makeVisible('report');
        if (is_null($participantResult->report)) {
            // Generate the report data
            $__report = new ParticipantReportService($participantResult->participant, $participantResult->competitionLevel);
            $report = $__report->getJsonReport();
            $participantResult->report = $report;
            $participantResult->save();
        } else {
            $report = $participantResult->report;
        }
        $report['general_data']['is_private'] = $participantResult->participant->tuition_centre_id ? true : false;
            $pdf = \PDF::loadView('performance-report', [
                'general_data'                  => $report['general_data'],
                'performance_by_questions'      => $report['performance_by_questions'],
                'performance_by_topics'         => $report['performance_by_topics'],
                'grade_performance_analysis'    => $report['grade_performance_analysis'],
                'analysis_by_questions'         => $report['analysis_by_questions']
            ]);
            $filename = $participantResult->participant->name . '-report.pdf';
            $pdfContent = $pdf->output();
            return view('performance-report-pdf')->with('pdfContent', $pdfContent)->with('filename', $filename);
    }
}
