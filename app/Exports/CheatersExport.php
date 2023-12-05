<?php

namespace App\Exports;

use App\Helpers\CheatingListHelper;
use App\Http\Requests\Competition\CompetitionCheatingListRequest;
use App\Models\Competition;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;


class CheatersExport implements FromCollection, WithStyles
{
    function __construct(
        private Competition $competition,
        private CompetitionCheatingListRequest $request
    ){}

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $dataCollection = CheatingListHelper::getCheatersDataForCSV($this->competition, $this->request);
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
        
        if($dataCollection->isNotEmpty()) {
            $header = array_keys($dataCollection->max());
        }
        
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
