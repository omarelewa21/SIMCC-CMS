<?php

namespace App\Exports;

use App\Models\Grade;
use App\Services\Competition\ParticipantAnswersListService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AnswerReportPerGrade implements FromCollection, WithHeadings, WithColumnFormatting, ShouldAutoSize, WithTitle
{
    private ParticipantAnswersListService $participantAnswersService;
    private string $grade;

    public function __construct(ParticipantAnswersListService $participantAnswersService, string $grade)
    {
        $this->participantAnswersService = $participantAnswersService;
        $this->grade = $grade;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->participantAnswersService->getAnswerReportData($this->grade);
    }

    public function headings(): array
    {
        return $this->participantAnswersService->getAnswerReportHeaders($this->grade);
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function title(): string
    {
        return Grade::whereId($this->grade)->value('display_name');
    }
}
