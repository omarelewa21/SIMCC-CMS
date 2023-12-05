<?php

namespace App\Exports;

use App\Exports\Sheets\CheatersSheet;
use App\Exports\Sheets\SameParticipantCheatersSheet;
use App\Http\Requests\Competition\CompetitionCheatingListRequest;
use App\Models\Competition;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CheatersExport implements WithMultipleSheets
{
    function __construct(
        private Competition $competition,
        private CompetitionCheatingListRequest $request
    ){}

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets[0] = new CheatersSheet($this->competition, $this->request);
        $sheets[1] = new SameParticipantCheatersSheet($this->competition, $this->request);

        return $sheets;
    }

    
}
