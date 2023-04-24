<?php

namespace App\Services;

use App\Custom\Marking;
use App\Http\Requests\Competition\CompetitionCheatingListRequest;
use App\Models\CheatingParticipants;
use App\Models\CheatingStatus;
use App\Models\Competition;

class CompetitionService
{
    /**
     * Validate if competition is ready to compute
     * 
     * @param Competition $competition
     */
    public static function validateIfCanGenerateCheatingPage(Competition $competition)
    {
        $competition->rounds()->with('levels')->get()
            ->pluck('levels')->flatten()
            ->each(function($level){
                if(Marking::isLevelReadyToCompute($level) === false) {
                    throw new \Exception(
                        sprintf("Level %s is not ready to compute. Check that all tasks has correct answers, round has awards and answers are uploaded to that level", $level->name),
                        400
                    );
                }
            });
    }

    public static function returnCheatStatusAndData(Competition $competition, CompetitionCheatingListRequest $request)
    {
        $cheatingStatus = CheatingStatus::findOrFail($competition->id);

        if($cheatingStatus->status === 'In Progress') {
            return response()->json([
                'status'    => 206,
                'message'   => 'Generating cheating list is in progress',
                'cheating_percentage' => $cheatingStatus->progress_percentage
            ], 206);
        }

        if($cheatingStatus->status === 'Failed') {
            return response()->json([
                'status'    => 500,
                'message'   => sprintf("Generating cheating list failed at perentage %s with error: ", $cheatingStatus->progress_percentage, $cheatingStatus->compute_error_message)
            ], 500);
        }

        if($cheatingStatus->status === 'Completed') {
            $cheaters = static::getCheatingList($competition)
                ->filterByRequest(
                    $request,
                    array("country", "school", "grade", "cheating_percentage", "group_id"),
                    array('participants', 'participants', 'school', 'country')
                );

            $filterOptions = static::getFilterOptions($cheaters);

            return response()->json([
                'status'    => 200,
                'message'   => 'Cheating list generated successfully',
                'filter_options' => $filterOptions,
                'Cheaters'  => $cheaters->paginate($request->limits ?? 10, $request->page ?? 1)
            ], 200);
        }
    }

    /**
     * Get cheating list
     * 
     * @param Competition $competition
     * @return Illuminate\Support\Collection
     */
    public static function getCheatingList(Competition $competition)
    {
        $groups = CheatingParticipants::where('competition_id', $competition->id)
            ->selectRaw(
                "*,
                AVG(number_of_cheating_questions) AS avg_cheating_questions_number,
                AVG(cheating_percentage) AS avg_cheating_percentage_percentage"
            )->groupBy('group_id')
            ->get()
            ->mapWithKeys(function($group){
                $cheatersGroupData = [];
                $cheatingParticipants = CheatingParticipants::where(['competition_id' => $group->competition_id, 'group_id' => $group->group_id])
                    ->with('participant.school', 'participant.country', 'otherParticipant.school', 'otherParticipant.country')
                    ->get();

                $firstRecordParticipant = $cheatingParticipants->first()->participant;
                $cheatersGroupData['number_of_questions'] = $firstRecordParticipant->answers()->count();
                $cheatersGroupData['cheating_percentage'] = round($group->avg_cheating_percentage_percentage);
                $cheatersGroupData['number_of_cheating_questions'] = round($group->avg_cheating_questions_number);
                $cheatersGroupData['school'] = $firstRecordParticipant->school->name;
                $cheatersGroupData['country'] = $firstRecordParticipant->country->display_name;
                $cheatersGroupData['grade'] = $firstRecordParticipant->grade;
                $cheatersGroupData['group_id'] = $group->group_id;
                $cheatersGroupData['participants'] = $cheatingParticipants->map(function($cheatingParticipant){
                    return [
                        [
                            'index' => $cheatingParticipant->participant->index_no,
                            'name'  => $cheatingParticipant->participant->name, 
                        ],
                        [
                            'index' => $cheatingParticipant->otherParticipant->index_no,
                            'name'  => $cheatingParticipant->otherParticipant->name
                        ]
                    ];
                })->flatten(1)->toArray();

                return [$group->group_id => $cheatersGroupData];
            });

        return $groups;
    }

    /**
     * Get filter options For cheating list
     * 
     * @param Illuminate\Support\Collection $cheaters
     * 
     */
    public static function getFilterOptions($cheaters)
    {
        $filterOptions = [
            'country' => $cheaters->pluck('country')->unique()->values(),
            'school' => $cheaters->pluck('school')->unique()->values(),
            'grade' => $cheaters->pluck('grade')->unique()->values(),
            'cheating_percentage' => $cheaters->pluck('cheating_percentage')->unique()->values(),
            'number_of_cheating_questions' => $cheaters->pluck('number_of_cheating_questions')->unique()->values(),
        ];

        return $filterOptions;
    }
}
