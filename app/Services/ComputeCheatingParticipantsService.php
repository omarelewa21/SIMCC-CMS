<?php

namespace App\Services;

use App\Models\CheatingParticipants;
use App\Models\CheatingStatus;
use App\Models\Competition;
use App\Models\ParticipantsAnswer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ComputeCheatingParticipantsService
{
    protected $cheatStatus;
    protected $qNumber;         // If cheating question number >= $qNumber, then the participant is considered as cheater
    protected $percentage;      // If cheating percentage >= $percentage, then the participant is considered as cheater
    protected $numberOFSameIncorrect; // If the number of same incorrect answers > $numberOFSameIncorrect, then the participant is considered as cheater
    protected $countryId;       // If countryId is not null, then only participants from the country will be considered

    /**
     * @param Competition $competition
     */
    public function __construct(protected Competition $competition, $qNumber=null, $percentage=95, $numberOFSameIncorrect = 1, $countryId = null)
    {
        $this->cheatStatus = CheatingStatus::findOrFail($this->competition->id);
        $this->qNumber = $qNumber;
        $this->percentage = $percentage;
        $this->numberOFSameIncorrect = $numberOFSameIncorrect;
        $this->countryId = $countryId;
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
                    ->when($this->countryId, fn($query) => $query->whereHas('participant', fn($query) => $query->where('country_id', $this->countryId)))
                    ->whereNull('is_correct')
                    ->chunkById(50000, function ($participantAnswers) use($level){
                        foreach ($participantAnswers as $participantAnswer) {
                            $participantAnswer->is_correct = $participantAnswer->getIsCorrectAnswer();
                            $participantAnswer->score = $participantAnswer->getAnswerMark();
                            $participantAnswer->save();
                        }
                    });
                });

            $this->updateCheatStatus(40);
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

        $this->updateCheatStatus(90);

        $this->detectCheatersWhoTookCompetitionTwiceOrMore();
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

        $this->competition->participants()
            ->when($this->countryId, fn($query) => $query->where('participants.country_id', $this->countryId))
            ->select('participants.*')
            ->with(['answers' => fn($query) => $query->orderBy('task_id')])
            ->withCount('answers')
            ->having('answers_count', '>', 0)
            ->cursor()->each(function ($participant) use ($groups) {
                $key = $participant->country_id . '-' . $participant->school_id . '-' . $participant->grade;
                if($groups->has($key))
                    $groups->get($key)->push($participant);
                else
                    $groups->put($key, collect([$participant]));
            });

        $this->updateCheatStatus(60);
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

        if($dataArray['number_of_same_incorrect_answers'] > $this->numberOFSameIncorrect && $this->shouldCreateCheatingParticipant($dataArray) ) {
            $dataArray['different_question_ids'] = $participant1->answers->whereNotIn('task_id', $sameQuestionIds)->pluck('task_id')->toArray();
            $dataArray['group_id'] = $this->generateGroupId($participant1, false, $dataArray);
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
            ->when($this->countryId, fn($query) => $query->where('participants.country_id', $this->countryId))
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
            && !is_null($p1Answer->answer) && !empty($p1Answer->answer)
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
            'number_of_questions' => $participant1->answers_count,
            'number_of_same_correct_answers' => 0,
            'number_of_same_incorrect_answers' => 0,
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
    private function generateGroupId($participant1, $forSameParticipant = false, $dataArray = [])
    {
        $cheatingParticipantRecords = CheatingParticipants::where('participant_index', $participant1->index_no)
            ->orWhere('cheating_with_participant_index', $participant1->index_no)
            ->when($forSameParticipant, fn($query) => $query->where('is_same_participant', 1))
            ->get();

        if($forSameParticipant) {
            return $cheatingParticipantRecords->isNotEmpty()
                ? $cheatingParticipantRecords->first()->group_id
                : CheatingParticipants::generateNewGroupId();
        }

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

    /**
     * Detect cheater who took the competition twice or more
     * 
     * @return void
     */
    private function detectCheatersWhoTookCompetitionTwiceOrMore()
    {
        $groups = $this->groupParticipantsByNameCountryAndSchool();
        foreach($groups as $group){
            $this->storeGroupAsPotentialCaseOfSameParticipantTakeSameCompetitionTwice($group);
        }
    }

    /**
     * Group participants by name, country, and school
     * 
     * @return LazyCollection
     */
    private function groupParticipantsByNameCountryAndSchool(): LazyCollection
    {
        $groups = collect();

        $this->competition->participants()
            ->when($this->countryId, fn($query) => $query->where('participants.country_id', $this->countryId))
            ->select(
                'participants.index_no',
                'participants.name',
                'participants.country_id', 
                'participants.school_id',
                'participants.grade'
            )
            ->withCount('answers')
            ->cursor()->each(function ($participant) use ($groups) {
                $key = $participant->name . '-' . $participant->country_id . '-' . $participant->school_id;
                if($groups->has($key))
                    $groups->get($key)->push($participant);
                else
                    $groups->put($key, collect([$participant]));
            });

        return $groups->lazy()->filter(
            fn ($group) => $group->count() > 1
        )->values();
    }

    /**
     * Store group as potential case of same participant take same competition twice
     * 
     * @param Collection $group
     * 
     * @return void
     */
    private function storeGroupAsPotentialCaseOfSameParticipantTakeSameCompetitionTwice($group)
    {
        $groupId = $this->generateGroupId($group->first(), true);

        foreach($group as $index => $participant) {
            if(!$group->has($index+1)) break;

            $otherParticipant = $group->get($index+1);
            $data = $this->getDefaultDataArray($participant, $otherParticipant);
            $data['group_id'] = $groupId;
            $data['is_same_participant'] = true;
            CheatingParticipants::create($data);
        }
    }
}
