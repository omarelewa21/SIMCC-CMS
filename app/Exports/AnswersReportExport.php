<?php

namespace App\Exports;

use App\Models\Competition;
use App\Services\Competition\ParticipantAnswersListService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AnswersReportExport implements FromCollection, WithHeadings, WithColumnFormatting, ShouldAutoSize
{
    private ParticipantAnswersListService $participantAnswersService;

    public function __construct(Competition $competition, Request $request)
    {
        $this->participantAnswersService = new ParticipantAnswersListService($competition, $request);
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->participantAnswersService->getAnswerReportData();
    }

    public function headings(): array
    {
        return $this->participantAnswersService->getAnswerReportHeaders();
    }

    public function columnFormats(): array
    {
        return [
            // A is string
            'A' => NumberFormat::FORMAT_TEXT,
        ];
    }
}
