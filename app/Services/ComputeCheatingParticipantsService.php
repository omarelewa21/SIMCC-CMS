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

    /**
     * @param Competition $competition
     */
    public function __construct(protected Competition $competition)
    {
        $this->cheatStatus = CheatingStatus::findOrFail($this->competition->id);
    }

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
                    ->chunkById(100, function ($participantAnswers) use($level){
                        foreach ($participantAnswers as $participantAnswer) {
                            $participantAnswer->is_correct = $participantAnswer->getIsCorrectAnswer($level->id);
                            $participantAnswer->score = $participantAnswer->getAnswerMark($level->id);
                            $participantAnswer->save();
                        }
                    });
                });

            $this->updateCheatStatus(30, 'In Progress');
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
        return Participants::join('competition_organization', 'participants.competition_organization_id', '=', 'competition_organization.id')
            ->where('competition_organization.competition_id', $this->competition->id)
            ->select('participants.*')
            ->with(['answers' => fn($query) => $query->orderBy('task_id')])
            ->get()
            ->groupBy(function ($participant) {
                return $participant->country_id . '-' . $participant->school_id . '-' . $participant->grade;
            })->filter(function ($group) {
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
    private function compareAnswersBetweenTwoParticipants($participant, $otherParticipant)
    {
        $numOfMatchAnswers = 0;
        $countOfAllAnswers = $participant->answers->isNotEmpty() ? $participant->answers->count() : 1;
        $participant->answers->each(function($participantAnswer) use($otherParticipant, &$numOfMatchAnswers){
            $otherAnswer = $otherParticipant->answers->first(
                fn($otherParticipantAnswer) => $participantAnswer->task_id === $otherParticipantAnswer->task_id
            );
            if($otherAnswer && $participantAnswer->is_correct === $otherAnswer->is_correct){
                $numOfMatchAnswers++;
            }
        });

        if($numOfMatchAnswers/$countOfAllAnswers >= 0.95){
            $groupId = CheatingParticipants::where('participant_index', $participant->index_no)
                ->orWhere('cheating_with_participant_index', $participant->index_no)
                ->value('group_id');

            CheatingParticipants::create([
                'competition_id'                    => $this->competition->id,
                'participant_index'                 => $participant->index_no,
                'cheating_with_participant_index'   => $otherParticipant->index_no,
                'group_id'                          => $groupId ?? CheatingParticipants::$nextGroupId++,
                'number_of_cheating_questions'      => $numOfMatchAnswers,
                'cheating_percentage'               => round(($numOfMatchAnswers/$countOfAllAnswers) * 100, 2)
            ]);
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
}
