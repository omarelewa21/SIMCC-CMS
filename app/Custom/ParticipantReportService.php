<?php

namespace App\Custom;

use App\Models\CompetitionLevels;
use App\Models\Participants;
use App\Models\ParticipantsAnswer;
use Illuminate\Support\Collection;

class ParticipantReportService
{
    protected CompetitionLevels $level;
    protected Participants $participant;

    function __construct(Participants $participant, CompetitionLevels $level)
    {
        $this->level = $level->load('rounds.competition');
        $this->participant = $participant->load('school');
    }

    public function getGeneralData(): array
    {
        return [
            'competition'       => $this->level->rounds->competition->name,
            'particiapnt'       => $this->participant->name,
            'school'            => $this->participant->school->name,
            'grade'             => sprintf("%s %s", "Grade", $this->participant->grade)
        ];
    }

    public function getPerformanceByQuestionsData(): Collection
    {
        return $this->answers->mapWithKeys(function($answer, $key){
            return [
                sprintf("%s %s", "Q", $key+1) =>
                    $answer->is_correct ? $answer->is_correct : $answer->getIsCorrectAnswerAttribute()
            ];
        });
    }

    public function getPerformanceByTopicsData()
    {
        $answers = ParticipantsAnswer::where('level_id', $this->level->id)
            ->join('participants', 'participant_answers.participant_index', 'participants.index_no')
            ->join('tasks', 'tasks.id', 'participant_answers.task_id')
            ->join('taggables', function ($join){
                $join->on('tasks.id', 'taggables.taggable_id')
                    ->where('taggables.taggable_type', 'App\Models\Tasks');
            })
            ->join('domains_tags', function ($join){
                $join->on('taggables.domains_tags_id', 'domains_tags.id')
                    ->whereNotNull('domains_tags.domain_id');
            })
            ->where(function($query){
                $query->where('participants.school_id', $this->participant->school_id)
                    ->orWhere('participants.country_id', $this->participant->country_id);
            })
            ->select(
                'participant_answers.*',
                'domains_tags.name AS topic',
                'participants.school_id',
                'participants.country_id'
            )->get();

        return $answers;
    }

    public function getGradePerformanceAnalysisData()
    {
        # code...
    }

    public function getAnalysisByQuestionsData()
    {
        return $this->getParticipantAnswers();
    }

    public function getParticipantAnswers(): Collection
    {
        return
        ParticipantsAnswer::where([
            ['participant_answers.level_id', $this->level->id],
            ['participant_answers.participant_index', $this->participant->index_no]
        ])
        ->join('tasks', 'tasks.id', 'participant_answers.task_id')
        ->join('competition_task_difficulty', function ($join){
            $join->on('tasks.id', 'competition_task_difficulty.task_id')
                ->where('competition_task_difficulty.level_id', $this->level->id);
        })
        ->select(
            'participant_answers.*',
            'competition_task_difficulty.difficulty'
        )->get()
        ->map(function ($answer){
            $answer->setAttribute('topics', $this->getParticipantAnswerTopics($answer));
            $answer->setAttribute('correct_in_school', $this->getParticipantAnswerCorrectInSchoolPercentage($answer));
        });

        // return ParticipantsAnswer::where(
        //     [ ['level_id', $this->level->id], ['participant_index', $this->participant->index_no] ]
        // )->distinct('task_id')->with([
        //     'task.taskTags' => fn($query) => $query->whereNotNull('domain_id')
        // ])->get();
    }

    public function getJsonReport(): string|false
    {
        return json_encode([
            "general_data"                  => $this->getGeneralData(),
            "performance_by_questions"      => $this->getPerformanceByQuestionsData(),
            "performance_by_topics"         => $this->getPerformanceByTopicsData(),
            "grade_performance_analysis"    => $this->getGradePerformanceAnalysisData(),
            "analysis_by_questions"         => $this->getAnalysisByQuestionsData(),
        ]);
    }

    public function getParticipantAnswerTopics(ParticipantsAnswer $answer): string|Null
    {
        return $answer->task->taskTags()->whereNotNull('domain_id')->implode('name', ', ');
    }

    public function getParticipantAnswerCorrectInSchoolPercentage(ParticipantsAnswer $answer): float
    {
        
    }
}