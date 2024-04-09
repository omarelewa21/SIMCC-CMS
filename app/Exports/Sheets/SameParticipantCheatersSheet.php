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
        $this->dataCollection = CheatingListHelper::getSameParticipantCheatersData($competition, $request, true);
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
        $headers = [];
        if($this->dataCollection->isNotEmpty()) {
            $headers = array_keys($this->dataCollection->max());
        }

        return [
            'Index',
            'Name',
            'School',
            'Country',
            'Grade',
            'System generated IAC',
            'Group ID',
            'No. Of Answers Uploaded',
            ...array_slice($headers, 9)
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