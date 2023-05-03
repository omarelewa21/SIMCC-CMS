<?php

namespace App\Services;

use App\Models\CheatingParticipants;
use App\Models\CheatingStatus;
use App\Models\Competition;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
use Illuminate\Support\Facades\DB;

class ComputeCheatingParticipantsService
{
    protected $cheatStatus;
    protected $qNumber;         // If cheating question number >= $qNumber, then the participant is considered as cheater
    protected $percentage;      // If cheating percentage >= $percentage, then the participant is considered as cheater

    /**
     * @param Competition $competition
     */
    public function __construct(protected Competition $competition, $qNumber=null, $percentage=95)
    {
        $this->cheatStatus = CheatingStatus::findOrFail($this->competition->id);
        $this->qNumber = $qNumber;
        $this->percentage = $percentage;
    }

    /**
     * Compute cheating participants
     * 
     * @return void
     */
    public function computeCheatingParticipants()
    {
        try {
            $this->clearRecords();
            $this->computeParticipantAnswersScores();
            $this->detectCheaters();

        } catch (\Exception $e) {
            $this->updateCheatStatus($this->cheatStatus->progress_percentage, 'Failed', $e->getMessage());
        }
    }
    
    /**
     * compute participant answers scores and update is_correct column
     * 
     * @return void
     */
    protected function computeParticipantAnswersScores()
    {
        DB::transaction(function(){
            $this->competition->rounds()->with('levels')->get()
                ->pluck('levels')->flatten()
                ->each(function($level){
                    ParticipantsAnswer::where('level_id', $level->id)
                    ->chunkById(50, function ($participantAnswers) use($level){
                        foreach ($participantAnswers as $participantAnswer) {
                            $participantAnswer->is_correct = $participantAnswer->getIsCorrectAnswer($level->id);
                            $participantAnswer->score = $participantAnswer->getAnswerMark($level->id);
                            $participantAnswer->save();
                        }
                    });
                });

            $this->updateCheatStatus(40, 'In Progress');
        });
    }

    /**
     * update competition_cheat_compute_status table with new status and progress percentage
     * 
     * @param int $progressPercentage
     * @param string $status
     * @param string|null $errorMessage
     * 
     * @return void
     */
    private function updateCheatStatus($progressPercentage, $status = 'In Progress', $errorMessage = null)
    {
        $this->cheatStatus->update([
            'status' => $status,
            'progress_percentage' => $progressPercentage,
            'compute_error_message' => $errorMessage
        ]);
    }

    /**
     * detect cheaters and store them in competition_cheating_participants table
     * 
     * @return void
     */
    protected function detectCheaters()
    {
        $this->groupParticipantsByCountrySchoolAndGrade()
            ->each(function ($group) {
                $this->compareAnswersBetweenParticipants($group);
            });
        
        $this->updateCheatStatus(100, 'Completed');
    }

    /**
     * Group participants by country, school, and grade
     * 
     * @return Collection
     */
    private function groupParticipantsByCountrySchoolAndGrade()
    {
        $groups = collect();

        Participants::join('competition_organization', 'participants.competition_organization_id', '=', 'competition_organization.id')
            ->where('competition_organization.competition_id', $this->competition->id)
            ->select('participants.*')
            ->with(['answers' => fn($query) => $query->orderBy('task_id')])
            ->cursor()->each(function ($participant) use ($groups) {
                $key = $participant->country_id . '-' . $participant->school_id . '-' . $participant->grade;
                if($groups->has($key))
                    $groups->get($key)->push($participant);
                else
                    $groups->put($key, collect([$participant]));
            });

        $this->updateCheatStatus(60, 'In Progress');
        return $groups->lazy()->filter(function ($group) {
            return $group->count() > 1;
        });
    }

    /**
     * Compare answers between participants in the group
     * 
     * @param Collection $group
     * 
     * @return void
     */
    private function compareAnswersBetweenParticipants($group)
    {
        $group->each(function ($participant, $participantKey) use ($group) {
            $group->each(function ($otherParticipant, $otherParticipantKey) use ($participant, $participantKey) {
                if ($participantKey < $otherParticipantKey) { // Avoid comparing participants with previous participants in the group 
                    $this->compareAnswersBetweenTwoParticipants($participant, $otherParticipant);
                }
            });
        });
    }

    /**
     * Compare answers between two participants and create cheating participants in cheating_participants table
     * 
     * @param Participant $participant
     * @param Participant $otherParticipant
     * 
     * @return void
     */
    private function compareAnswersBetweenTwoParticipants($participant1, $participant2)
    {
        $dataArray = $this->getDefaultDataArray($participant1, $participant2);
        $sameQuestionIds = [];
        foreach($participant1->answers as $p1Answer){
            $p2Answer = $participant2->answers
                ->first(fn($answer)=> $p1Answer->task_id === $answer->task_id);

            if( $this->isTwoAnswersMatch($p1Answer, $p2Answer) ) {
                $dataArray['number_of_cheating_questions']++;
                if ($p1Answer->is_correct) $dataArray['number_of_same_correct_answers']++;
                else $dataArray['number_of_same_incorrect_answers']++;

                $sameQuestionIds[] = $p1Answer->task_id;
            }
        }

        if( $this->shouldCreateCheatingParticipant($dataArray) ) {
            $dataArray['different_question_ids'] = $participant1->answers->whereNotIn('task_id', $sameQuestionIds)->pluck('task_id')->toArray();
            $dataArray['group_id'] = $this->generateGroupId($participant1, $participant2, $dataArray);
            $dataArray['cheating_percentage'] = ( $dataArray['number_of_cheating_questions'] / $dataArray['number_of_questions'] ) * 100;

            CheatingParticipants::create($dataArray);
        }
    }

    /**
     * Clear records in cheating_participants table
     * 
     * @return void
     */
    private function clearRecords()
    {
        CheatingParticipants::join('participants', 'cheating_participants.participant_index', '=', 'participants.index_no')
            ->join('competition_organization', 'participants.competition_organization_id', '=', 'competition_organization.id')
            ->where('competition_organization.competition_id', $this->competition->id)
            ->delete();
    }

    /**
     * Detect if two answers are match
     * 
     * @param ParticipantAnswer $p1Answer
     * @param ParticipantAnswer|null $p2Answer
     * 
     * @return bool
     */
    private function isTwoAnswersMatch($p1Answer, $p2Answer)
    {
        return $p2Answer
            && $p1Answer->answer == $p2Answer->answer
            && $p1Answer->is_correct === $p2Answer->is_correct;
    }

    /**
     * Get default data array
     * 
     * @param Participant $participant1
     * @param Participant $participant2
     * 
     * @return array
     */
    private function getDefaultDataArray($participant1, $participant2)
    {
        return [
            'competition_id'    => $this->competition->id,
            'participant_index' => $participant1->index_no,
            'cheating_with_participant_index' => $participant2->index_no,
            'number_of_cheating_questions' => 0,
            'number_of_questions' => $participant1->answers->count(),
            'number_of_same_correct_answers' => 0,
            'number_of_same_incorrect_answers' => 0,
            'number_of_correct_answers' => $participant1->answers->where('is_correct', 1)->count(),
            'different_question_ids' => [],
            'cheating_percentage' => 0,
        ];
    }

    /**
     * Detect if a cheating participant should be created
     * 
     * @param array $dataArray
     * 
     * @return bool
     */
    private function shouldCreateCheatingParticipant($dataArray)
    {
        return 
            (
                $this->qNumber && $this->qNumber > 0
                && $dataArray['number_of_cheating_questions'] >= $this->qNumber
            )
            ||
            (
                $dataArray['number_of_cheating_questions'] > 0 && $dataArray['number_of_questions'] > 0
                && ($dataArray['number_of_cheating_questions'] / $dataArray['number_of_questions']) * 100 >= $this->percentage
            );
    }

    /**
     * Generate group id
     * 
     * @param Participant $participant1
     * @param Participant $participant2
     * @param array $dataArray
     * 
     * @return string
     */
    private function generateGroupId($participant1, $participant2, $dataArray)
    {
        $cheatingParticipantRecords = CheatingParticipants::where('participant_index', $participant1->index_no)
                ->orWhere('cheating_with_participant_index', $participant2->index_no)
                ->get();

        foreach($cheatingParticipantRecords as $cheatingParticipant){
            if( $this->compareTwoDiffentQuestionArrays(
                    $cheatingParticipant->different_question_ids->getArrayCopy(),
                    $dataArray['different_question_ids']
                )
            ) {
                return $cheatingParticipant->group_id;
            }
        }

        return CheatingParticipants::generateNewGroupId();
    }

    /**
     * Compare two different question arrays and return true if they are same
     * 
     * @param array $differntQuestionArray
     * @param array $differentQuestionIds
     * 
     * @return bool
     */
    private function compareTwoDiffentQuestionArrays($differntQuestionArray, $differentQuestionIds)
    {
        if( count($differntQuestionArray) !== count($differentQuestionIds) ) return false;
        return empty(array_diff($differntQuestionArray, $differentQuestionIds));
    }
}
