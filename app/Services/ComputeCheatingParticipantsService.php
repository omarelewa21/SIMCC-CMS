<?php

namespace App\Services;

use App\Models\CheatingParticipants;
use App\Models\CheatingStatus;
use App\Models\Competition;
use App\Models\IntegrityCheckCompetitionCountries;
use App\Models\IntegritySummary;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\LazyCollection;

class ComputeCheatingParticipantsService
{
    protected CheatingStatus $cheatStatus;
    protected array $grades;

    /**
     * @param Competition $competition
     */
    public function __construct(
        protected Competition $competition,
        protected $qNumber=null,
        protected $percentage=85,
        protected $numberOFSameIncorrect=5,
        protected $countries = null,
        protected $forMapList = false,
        protected $userId = null,
    )
    {
        $this->cheatStatus = CheatingStatus::where([
            'competition_id'                    => $competition->id,
            'cheating_percentage'               => $percentage ?? 85,
            'number_of_same_incorrect_answers'  => $numberOFSameIncorrect ?? 5,
            'for_map_list'                      => $forMapList
        ])
        ->FilterByCountries($countries)
        ->firstOrFail();

        $this->grades = GradeService::getGradesWithVerifiedCollections($competition);
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

            $this->forMapList
                ? $this->detectCheatersWhoTookCompetitionTwiceOrMore()
                : $this->detectCheaters();

            $this->cheatStatus->total_cases_count = $this->getTotalCasesCount();
            $this->cheatStatus->save();
            $this->updateCheatStatus(100, 'Completed');
        }

        catch (\Exception $e) {
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
        $progress = 1;

        $this->competition->rounds()->with('levels')->get()
            ->pluck('levels')->flatten()
            ->each(function($level) use (&$progress){
                ParticipantsAnswer::where('level_id', $level->id)
                    ->when($this->countries, fn($query) => $query->whereHas('participant', fn($query) => $query->whereIn('country_id', $this->countries)))
                    ->when($this->grades, fn($query) => $query->whereHas('participant', fn($query) => $query->whereIn('grade', $this->grades)))
                    ->chunkById(3000, function ($participantAnswers) use(&$progress){
                        foreach ($participantAnswers as $participantAnswer) {
                            $participantAnswer->is_correct = $participantAnswer->getIsCorrectAnswer();
                            $participantAnswer->score = $participantAnswer->getAnswerMark();
                            $participantAnswer->save();
                        }
                        $progress = $progress <= 50 ? $progress + 5 : $progress;
                        $this->updateCheatStatus($progress);
                    });
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
        is_null($this->countries) && $this->setCountriesList();

        IntegrityCheckCompetitionCountries::setCompetitionCountries($this->competition, $this->countries);

        $this->groupParticipantsByCountrySchoolAndGrade()
            ->each(function ($group) {
                $this->compareAnswersBetweenParticipants($group);
            });

        IntegrityCheckCompetitionCountries::updateCountriesComputeStatus($this->competition, $this->countries);
        $this->addIntegritySummary();
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
            ->when($this->countries, fn($query) => $query->whereIn('participants.country_id', $this->countries))
            ->whereIn('participants.grade', $this->grades)
            ->select('participants.*')
            ->with(['answers' => fn($query) => $query->orderBy('task_id')])
            ->withCount('answers')
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
        $progress = 60;
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

        if($dataArray['number_of_same_incorrect_answers'] >= $this->numberOFSameIncorrect && $this->shouldCreateCheatingParticipant($dataArray) ) {
            $dataArray['different_question_ids'] = $participant1->answers->whereNotIn('task_id', $sameQuestionIds)->pluck('task_id')->toArray();
            $dataArray['group_id'] = $this->generateGroupId($participant1, false, $dataArray);
            $dataArray['cheating_percentage'] = ( $dataArray['number_of_cheating_questions'] / $dataArray['number_of_questions'] ) * 100;

            CheatingParticipants::create($dataArray);
        }
        $progress = $progress <= 50 ? $progress + 5 : $progress;
        $this->updateCheatStatus($progress);
    }

    /**
     * Clear records in cheating_participants table
     *
     * @return void
     */
    private function clearRecords()
    {
        CheatingParticipants::where([
                'competition_id'      => $this->competition->id,
                'is_same_participant' => $this->forMapList
            ])
            ->whereDoesntHave('integrityCases', fn($query) => $query->whereIn('mode', ['map', 'system']))
            ->whereDoesntHave('otherIntegrityCases', fn($query) => $query->whereIn('mode', ['map', 'system']))
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
            'criteria_cheating_percentage' => $this->percentage,
            'criteria_number_of_same_incorrect_answers' => $this->numberOFSameIncorrect,
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
            $this->participantsDoentExistBefore($dataArray) &&
            ($this->checkQNumber($dataArray) || $this->checkPercentage($dataArray));
    }

    /**
     * Check if qNumber is set and greater than 0
     *
     * @param array $dataArray
     *
     * @return void
     */
    private function checkQNumber($dataArray)
    {
        return $this->qNumber && $this->qNumber > 0 && $dataArray['number_of_cheating_questions'] >= $this->qNumber;
    }

    /**
     * Check if percentage is set
     *
     * @param array $dataArray
     *
     * @return void
     */
    private function checkPercentage($dataArray)
    {
        return $dataArray['number_of_cheating_questions'] > 0 && $dataArray['number_of_questions'] > 0
            && ($dataArray['number_of_cheating_questions'] / $dataArray['number_of_questions']) * 100 >= $this->percentage;
    }

    /**
     * Check if participants do not exist before
     *
     * @param array $dataArray
     *
     * @return bool
     */
    private function participantsDoentExistBefore($dataArray)
    {
        return CheatingParticipants::where(
            fn($query) => $query->where('participant_index', $dataArray['participant_index'])
                ->where('cheating_with_participant_index', $dataArray['cheating_with_participant_index'])
        )->orWhere(
            fn($query) => $query->where('cheating_with_participant_index', $dataArray['participant_index'])
                ->Where('participant_index', $dataArray['cheating_with_participant_index'])
        )->doesntExist();
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
            ->when($this->countries, fn($query) => $query->whereIn('participants.country_id', $this->countries))
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
        $progress = 50;
        $groupId = $this->generateGroupId($group->first(), true);

        foreach($group as $index => $participant) {
            if(!$group->has($index+1)) break;

            $otherParticipant = $group->get($index+1);
            $data = $this->getDefaultDataArray($participant, $otherParticipant);
            $data['group_id'] = $groupId;
            $data['is_same_participant'] = true;
            CheatingParticipants::create($data);

            $progress = $progress <= 90 ? $progress + 5 : $progress;
            $this->updateCheatStatus($progress);
        }
    }

    /**
     * Only compute countries that has participants with answers
     *
     * @return void
     */
    private function setCountriesList()
    {
        $this->countries = $this->competition->participants()
            ->has('answers')
            ->select('participants.country_id')
            ->distinct()
            ->pluck('country_id')
            ->toArray();
    }

    /**
     * Get total cases count
     *
     * @return int
     */
    private function getTotalCasesCount(): int
    {
        return Participants::join('cheating_participants', function (JoinClause $join) {
            $join->on('participants.index_no', 'cheating_participants.participant_index')
                ->orOn('participants.index_no', 'cheating_participants.cheating_with_participant_index');
            })
            ->where('cheating_participants.competition_id', $this->competition->id)
            ->where('cheating_participants.is_same_participant', $this->forMapList)
            ->when($this->countries, fn($query) => $query->whereIn('participants.country_id', $this->countries))
            ->whereDoesntHave('integrityCases', fn($query) => $query->where('mode', $this->forMapList ? 'map' : 'system'))
            ->count();
    }

    /**
     * Add integrity summary
     *
     * @return void
     */
    private function addIntegritySummary()
    {
        IntegritySummary::create([
            'competition_id'                    => $this->competition->id,
            'cheating_percentage'               => $this->percentage,
            'number_of_same_incorrect_answers'  => $this->numberOFSameIncorrect,
            'countries'                         => $this->countries,
            'computed_grades'                   => $this->grades,
            'remaining_grades'                  => $this->getRemainingGrades(),
            'total_cases_count'                 => $this->getTotalCasesCount(),
            'run_by'                            => $this->userId,
        ]);
    }

    /**
     * Get remaining grades
     *
     * @return array
     */
    private function getRemainingGrades(): array
    {
        $allCompetitionGrades = $this->getAllCompetitionGrades();
        return array_values(array_diff($allCompetitionGrades, $this->grades));
    }

    /**
     * Get all competition grades
     *
     * @return array
     */
    private function getAllCompetitionGrades(): array
    {
        return $this->competition->levels()->select('competition_levels.grades')
            ->pluck('grades')->flatten()->unique()->toArray();
    }
}
