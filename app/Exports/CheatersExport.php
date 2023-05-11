<?php

namespace App\Exports;

use App\Helpers\CheatingListHelper;
use App\Models\Competition;
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
        $dataCollection = CheatingListHelper::getCheatersDataForCSV($this->competition);
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
        $header = array_keys($dataCollection->first());
        $header[0] = 'Index';
        $header[1] = 'Name';
        $header[2] = 'School';
        $header[3] = 'Country';
        $header[4] = 'Grade';
        $header[5] = 'Group ID';
        $header[6] = 'No of qns';
        $header[7] = 'No of qns with same answer';
        $header[8] = 'No of qns with same answer percentage'; 
        $header[9] = 'No of qns with same correct answer';
        $header[10] = 'No of qns with same incorrect answer';
        $header[11] = 'No of correct answers';
        $header[12] = 'Qns with same incorrect answer';
        return $returnedCollection->prepend($header);
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text.
            1    => ['font' => ['bold' => true]],
        ];
    }
}
