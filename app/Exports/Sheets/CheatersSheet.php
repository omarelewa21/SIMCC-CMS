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

class CheatersSheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    private $dataCollection;

    function __construct(Competition $competition, CompetitionCheatingListRequest $request)
    {
        $this->dataCollection = CheatingListHelper::getCheatersData($competition, $request, true);
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
            'School',
            'Country',
            'Grade',
            'System generated IAC',
            'Reason',
            'IAC Created By',
            'Criteria Matching Answers Percentage',
            'Criteria No of Same Incorrect Answers',
            'Group ID',
            'No of qns',
            'No of qns with same answer',
            'No of qns with same answer percentage',
            'No of qns with same correct answer',
            'No of qns with same incorrect answer',
            'No of correct answers',
            'Qns with same incorrect answer',
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
        return 'Integrity List';
    }
}
