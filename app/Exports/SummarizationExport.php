<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class SummarizationExport implements FromCollection, WithHeadings, WithTitle
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
        return collect($this->sheetData);
    }

    public function headings(): array
    {
        return [
            'Index Number',
            'Grade',
            'Status'
        ];
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    private function formatTitle($title)
    {
        return ucfirst(str_replace('_', ' ', $title));
    }
}
