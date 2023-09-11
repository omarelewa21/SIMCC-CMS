<?php

namespace App\Custom;

use App\Models\CompetitionLevels;
use App\Models\DomainsTags;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ParticipantReportService
{
    protected CompetitionLevels $level;
    protected Participants $participant;
    protected Collection $getAnalysisByQuestionData;

    function __construct(Participants $participant, CompetitionLevels $level)
    {
        $this->level = $level->load('rounds.competition');
        $this->participant = $participant->load('school');
        $this->getAnalysisByQuestionData = $this->getAnalysisByQuestionsData();
    }

    public function getGeneralData()
    {
        $schoolName = $this->participant->school();
        if ($schoolName->exists()) {
            $schoolName = $schoolName->value('name_in_certificate') ?? $schoolName->value('name');
        } else {
            $schoolName = 'No school specified for this participant';
        }
        return [
            'competition' => $this->level->rounds->competition->name,
            'participant' => $this->participant->name,
            'school' => $schoolName,
            'grade' => sprintf("Grade %s", $this->participant->grade)
        ];
    }


    public function getPerformanceByQuestionsData(): Collection
    {
        return
            ParticipantsAnswer::where([
                ['participant_answers.level_id', $this->level->id],
                ['participant_answers.participant_index', $this->participant->index_no]
            ])->get()
            ->flatMap(function ($answer, $key) {
                return [
                    sprintf('Q%s', $key + 1)  => $answer->is_correct ?? $answer->getIsCorrectAnswer($this->level->id)
                ];
            });
    }

    public function getPerformanceByTopicsData(): Collection
    {
        return
            $this->getAnalysisByQuestionData->pluck('topic')
            ->flatten()
            ->unique('id')
            ->values()
            ->map(function ($topic) {
                $filteredData = $this->getFilteredAnalysisByQuestionDataByTopicName($topic->id);
                return [
                    'domain'        => $topic->domain->name,
                    'topic'         => $topic->name,
                    'participant'   => round($filteredData->sum('is_correct') / $filteredData->count() * 100),
                    'school'        => round($filteredData->sum('correct_in_school') / $filteredData->count()),
                    'country'       => round($filteredData->sum('correct_in_country') / $filteredData->count())
                ];
            });
    }

    public function getGradePerformanceAnalysisData()
    {
        return
            ParticipantsAnswer::where([
                ['participant_answers.level_id', $this->level->id],
                ['participant_answers.participant_index', $this->participant->index_no]
            ])
            ->join('tasks', 'tasks.id', 'participant_answers.task_id')
            ->join('taggables', function ($join) {
                $join->on('tasks.id', 'taggables.taggable_id')
                    ->where('taggables.taggable_type', 'App\Models\Tasks');
            })
            ->join('domains_tags', function ($join) {
                $join->on('taggables.domains_tags_id', 'domains_tags.id')
                    ->whereNotNull('domains_tags.domain_id');
            })
            ->select('domains_tags.id', 'domains_tags.name', 'domains_tags.domain_id')
            ->distinct('domains_tags.id')->get()
            ->map(function ($topic) {
                $schoolData = $this->getParticipantSchoolStatisticsByTopic($topic->id, $this->participant->index_no);
                return [
                    'domain'                => DomainsTags::whereId($topic->domain_id)->value('name'),
                    'topic'                 => $topic->name,
                    'participant_score'     => $schoolData->participantScore,
                    'school_range'          => sprintf("%s-%s", $schoolData->minScore, $schoolData->maxScore),
                    'school_average'        => $schoolData->averageScore
                ];
            });
    }

    public function getAnalysisByQuestionsDataProcessed(): Collection
    {
        return
            $this->getAnalysisByQuestionData->map(function ($data) {
                $data['topic'] = $data['topic']->implode('name', ', ');
                return $data;
            });
    }

    public function getJsonReport(): array
    {
        return [
            "general_data"                  => $this->getGeneralData(),
            "performance_by_questions"      => $this->getPerformanceByQuestionsData(),
            "performance_by_topics"         => $this->getPerformanceByTopicsData(),
            "grade_performance_analysis"    => $this->getGradePerformanceAnalysisData(),
            "analysis_by_questions"         => $this->getAnalysisByQuestionsDataProcessed(),
        ];
    }


    /**
     ******************************************************* Helpers ******************************************************
     */

    private function getParticipantAnswerTopics(ParticipantsAnswer $answer): Collection
    {
        return $answer->task->taskTags()->whereNotNull('domain_id')
            ->select('domains_tags.id', 'domains_tags.name', 'domains_tags.domain_id')->get();
    }

    private function getParticipantAnswerCorrectInSchoolPercentage(ParticipantsAnswer $answer): int
    {
        $allAnswers = ParticipantsAnswer::where([
            ['participant_answers.level_id', $answer->level_id],
            ['participant_answers.task_id', $answer->task_id]
        ])
            ->join('participants', 'participants.index_no', 'participant_answers.participant_index')
            ->where('participants.school_id', $answer->participant->school_id)
            ->get()->map(function ($answer) {
                $answer->is_correct_answer = $answer->is_correct ?? $answer->getIsCorrectAnswer($this->level->id);
                return $answer;
            });

        return round($allAnswers->sum('is_correct_answer') / $allAnswers->count() * 100);
    }

    private function getParticipantAnswerCorrectInCountryPercentage(ParticipantsAnswer $answer): int
    {
        $allAnswers = ParticipantsAnswer::where([
            ['participant_answers.level_id', $answer->level_id],
            ['participant_answers.task_id', $answer->task_id]
        ])
            ->join('participants', 'participants.index_no', 'participant_answers.participant_index')
            ->where('participants.country_id', $answer->participant->country_id)
            ->get()->map(function ($answer) {
                $answer->is_correct_answer = $answer->is_correct ?? $answer->getIsCorrectAnswer($this->level->id);
                return $answer;
            });

        return round($allAnswers->sum('is_correct_answer') / $allAnswers->count() * 100);
    }

    private function getFilteredAnalysisByQuestionDataByTopicName(int $topicId): Collection
    {
        return
            $this->getAnalysisByQuestionData->filter(
                fn ($data) => $data['topic']->filter(fn ($topic) => $topic->id === $topicId)->isNotEmpty()
            );
    }

    private function getParticipantSchoolStatisticsByTopic(int $topicId, string $participantIndex = null): object
    {
        $allAnswers = ParticipantsAnswer::where('participant_answers.level_id', $this->level->id)
            ->join('participants', 'participants.index_no', 'participant_answers.participant_index')
            ->where('participants.school_id', $this->participant->school_id)
            ->join('tasks', 'tasks.id', 'participant_answers.task_id')
            ->join('taggables', function ($join) {
                $join->on('tasks.id', 'taggables.taggable_id')
                    ->where('taggables.taggable_type', 'App\Models\Tasks');
            })
            ->join('domains_tags', function ($join) {
                $join->on('taggables.domains_tags_id', 'domains_tags.id')
                    ->whereNotNull('domains_tags.domain_id');
            })
            ->where('domains_tags.id', $topicId)
            ->select(
                'participants.index_no',
                DB::raw('SUM(participant_answers.score) AS sum')
            )->groupBy('participants.index_no')
            ->get();

        $participantScore = !is_null($participantIndex)
            ? $allAnswers->filter(fn ($answer) => $answer->index_no === $participantIndex)->first()->sum
            : Null;

        return (object) [
            'minScore'          => $allAnswers->min('sum'),
            'maxScore'          => $allAnswers->max('sum'),
            'averageScore'      => $allAnswers->avg('sum'),
            'participantScore'  => $participantScore
        ];
    }

    private function getAnalysisByQuestionsData(): Collection
    {
        return
            ParticipantsAnswer::where([
                ['participant_answers.level_id', $this->level->id],
                ['participant_answers.participant_index', $this->participant->index_no]
            ])
            ->join('tasks', 'tasks.id', 'participant_answers.task_id')
            ->join('competition_task_difficulty', function ($join) {
                $join->on('tasks.id', 'competition_task_difficulty.task_id')
                    ->where('competition_task_difficulty.level_id', $this->level->id);
            })
            ->select(
                'participant_answers.*',
                'competition_task_difficulty.difficulty'
            )->get()
            ->map(function ($answer, $key) {
                return [
                    'question'              => sprintf("Q%s", $key + 1),
                    'topic'                 => $this->getParticipantAnswerTopics($answer),
                    'is_correct'            => $answer->is_correct ?? $answer->getIsCorrectAnswer($this->level->id),
                    'correct_in_school'     => $this->getParticipantAnswerCorrectInSchoolPercentage($answer),
                    'correct_in_country'    => $this->getParticipantAnswerCorrectInCountryPercentage($answer),
                    'diffculty'             => $answer->difficulty
                ];
            });
    }
}
