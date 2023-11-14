<?php 

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class InvalidAnswersExport implements FromCollection, WithHeadings, WithTitle
{
    private $sheetTitle;
    private $sheetData;

    public function __construct($sheetTitle, $sheetData)
    {
        $this->sheetTitle = $this->formatTitle($sheetTitle);
        $this->sheetData = $sheetData;
    }

    public function collection()
    {
        return collect($this->formatAnswers());
    }

    public function headings(): array
    {
        $firstParticipant = reset($this->sheetData);
        $numOfAnswers = count($firstParticipant['answers']);

        // Generate dynamic headings including 'Answers'
        $headings = [
            'Index Number',
            'Grade'
        ];

        for ($i = 1; $i <= $numOfAnswers; $i++) {
            $headings[] = 'Q' . $i;
        }

        return $headings;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    private function formatTitle($title)
    {
        return ucfirst(str_replace('_', ' ', $title));
    }

    private function formatAnswers()
    {
        $formattedData = [];

        foreach ($this->sheetData as $participant) {
            $formattedParticipant = [
                'Index Number' => $participant['index_number'],
                'Grade' => $participant['grade'],
            ];

            foreach ($participant['answers'] as $i => $answer) {
                $formattedParticipant['Q' . ($i + 1)] = $answer;
            }

            $formattedData[] = $formattedParticipant;
        }

        return $formattedData;
    }
}
