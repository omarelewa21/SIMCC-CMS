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
use App\Models\TasksAnswers;
use App\Services\GradeService;
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

    public function testLeftTrimmingZeroes(Request $request)
    {
        $countryId = 174;
        $levelId = 380;
        $taskId = 3062;
        $taskAnswerId = 10060;        

        $answers = ParticipantsAnswer::whereRelation('participant', 'country_id', $countryId)
            ->where('level_id', $levelId)
            ->where('task_id', $taskId)
            ->get();
        
        $taskAnswer = TasksAnswers::find($taskAnswerId);

        if($request->isMethod('post')){
            try {
                foreach($request->answers as $answer){
                    $answers->where('id', $answer['id'])->first()->update(['answer' => $answer['answer']]);
                }

                $taskAnswer->update(['answer' => $request->taskAnswer]);
                
                return response()->json(['message' => 'success'], 200);
                
            } catch (\Throwable $th) {
                return response()->json(['message' => $th->getMessage()], 500);
            }
        }
        
        
        return view('testLeftTrimmingZeros', compact('answers', 'taskAnswer'));
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
}
