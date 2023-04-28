<?php

namespace App\Exports;

use App\Models\Competition;
use App\Services\CompetitionService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class CheatersExport implements FromCollection, WithStyles
{

    private $competition;

    function __construct(Competition $competition)
    {
        $this->competition = $competition;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return CompetitionService::generateCheatersCSVFile($this->competition);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text.
            1    => ['font' => ['bold' => true]],
        ];
    }
}
