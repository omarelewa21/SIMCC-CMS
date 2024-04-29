<?php

namespace App\Exports\Sheets;

use App\Helpers\CheatingListHelper;
use App\Models\Competition;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IncidentSheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    private $dataCollection;

    function __construct(Competition $competition)
    {
        $this->dataCollection = CheatingListHelper::getCustomLabeledIntegrityCasesData($competition)
            ->map(function($data) {
                unset($data['school_id']);
                unset($data['country_id']);
                unset($data['laravel_through_key']);
                unset($data['iac_status']);
                foreach($data['answers'] as $key => $answer) {
                    $data["Q".($key+1)] = sprintf("%s %s", $answer['answer'], $answer['is_correct'] ? '(Correct)' : '(Wrong)');
                }
                unset($data['answers']);
                return $data;
            });
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return $this->dataCollection;
    }

    public function headings(): array
    {
        if($this->dataCollection->isNotEmpty()) {
            $headers = collect($this->dataCollection->first(fn($value) => $value == $this->dataCollection->max()))
                ->filter(fn($value, $key) => preg_match('/^Q\d+$/', $key))
                ->keys()
                ->toArray();
        } else {
            $headers = [];
        }

        return [
            'Index',
            'Name',
            'Grade',
            'School',
            'Country',
            'Reason',
            'IAC Created By',
            'IAC Created Date/Time (UTC)',
            ...$headers,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text.
            1    => ['font' => ['bold' => true]],
        ];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'IAC Indcidents';
    }
}
