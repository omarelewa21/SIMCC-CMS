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
    protected Collection $answers;

    function __construct(Participants $participant, CompetitionLevels $level)
    {
        $this->level = $level->load('rounds.competition');
        $this->participant = $participant->load('school');
        $this->answers = $this->getParticipantAnswers();
    }

    public function getGeneralData()
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
            return [sprintf("%s %s", "Q", $key+1) => $answer['is_correct_answer']];
        });
    }

    public function getPerformanceByTopicsData()
    {
        # code...
    }

    public function getGradePerformanceAnalysisData()
    {
        # code...
    }

    public function getAnalysisByQuestionsData()
    {
        # code...
    }

    public function getParticipantAnswers(): Collection
    {
        return ParticipantsAnswer::where(
            [ ['level_id', $this->level->id], ['participant_index', $this->participant->index_no] ]
        )->distinct('task_id')->get()
        ->map(function ($answer) {
            return $answer->setAttribute('is_correct_answer', $answer->isCorrectAnswer());
        });
    }
}