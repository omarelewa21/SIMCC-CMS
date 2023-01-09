<?php

namespace App\Custom;

use App\Models\CompetitionLevels;
use App\Models\Participants;

class ParticipantReportService
{
    protected CompetitionLevels $level;
    protected Participants $participant;

    function __construct(Participants $participant, CompetitionLevels $level)
    {
        $this->level = $level->load('rounds.competition');
        $this->participant = $participant->load('school');
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

    public function getPerformanceByQuestionsData(): object
    {
        
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
}