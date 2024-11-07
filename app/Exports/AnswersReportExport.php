<?php

namespace App\Exports;

use App\Models\Competition;
use App\Services\Competition\ParticipantAnswersListService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class AnswersReportExport implements WithMultipleSheets
{
    private ParticipantAnswersListService $participantAnswersService;
    private Request $request;

    public function __construct(Competition $competition, Request $request)
    {
        $this->participantAnswersService = new ParticipantAnswersListService($competition, $request);
        $this->request = $request;
    }

    public function sheets(): array
    {
        $sheets = [];
        if($this->request->filled('grade')) {
            $sheets[] = new AnswerReportPerGrade($this->participantAnswersService, $this->request->grade);
            return $sheets;
        }

        $grades = $this->participantAnswersService->getCompetitionsGrades();
        foreach($grades as $grade) {
            $sheets[] = new AnswerReportPerGrade($this->participantAnswersService, $grade);
        }
        return $sheets;
    }
}
