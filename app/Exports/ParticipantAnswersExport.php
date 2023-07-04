<?php

namespace App\Exports;

use App\Models\Competition;
use App\Models\ParticipantsAnswer;
use App\Services\CompetitionService;
use Maatwebsite\Excel\Concerns\FromCollection;

class ParticipantAnswersExport implements FromCollection
{

    protected Competition $competition;

    function __construct(Competition $competition) {
        $this->competition = $competition;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        [$data, $headers] = CompetitionService::getCompetitionAnswersData($this->competition);
        return collect($data)->prepend($headers);
    }
}
