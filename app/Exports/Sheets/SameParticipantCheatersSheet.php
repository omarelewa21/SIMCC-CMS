<?php

namespace App\Exports\Sheets;

use App\Helpers\CheatingListHelper;
use App\Http\Requests\Competition\CompetitionCheatingListRequest;
use App\Models\Competition;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SameParticipantCheatersSheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    private $dataCollection;

    function __construct(Competition $competition, CompetitionCheatingListRequest $request)
    {
        $this->dataCollection = CheatingListHelper::getSameParticipantCheatersDataForCSV($competition, $request);
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $returnedCollection = collect();
        $lastGroup = null;
        foreach($this->dataCollection as $key=>$record) {
            if($key !== 0 && $lastGroup !== $record['group_id']) {
                $returnedCollection->push(['-'], $record);
            }else{
                $returnedCollection->push($record);
            }
            $lastGroup = $record['group_id'];
        }

        return $returnedCollection;
    }

    public function headings(): array
    {
        return [
            'Index',
            'Name',
            'School',
            'Country',
            'Grade',
            'Group ID',
            'No of answers uploaded',
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
        return 'Same Students Participating Multiple Times';
    }
}