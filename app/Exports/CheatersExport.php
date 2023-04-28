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
        $dataCollection = CompetitionService::getCheatersDataForCSV($this->competition);
        $returnedCollection = collect();
        $lastGroup = null;
        foreach($dataCollection as $key=>$record) {
            if($key !== 0 && $lastGroup !== $record['group_id']) {
                $returnedCollection->push(['-'], $record);
            }else{
                $returnedCollection->push($record);
            }
            $lastGroup = $record['group_id'];
        }
        return $returnedCollection->prepend(array_keys($dataCollection->first()));
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text.
            1    => ['font' => ['bold' => true]],
        ];
    }
}
