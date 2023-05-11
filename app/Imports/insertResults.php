<?php

namespace App\Imports;

use App\Models\Competition;
use App\Models\CompetitionParticipantsResults;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;


class insertResults implements ToModel, WithHeadingRow
{

    public function model(array $row)
    {
        $result = CompetitionParticipantsResults::updateOrCreate(
            ['participant_index' => $row['index_no'], 'level_id'  => $row['level_id']],
            [
            'ref_award' => $row['award'],
            'award' => $row['award'],
            'points' => $row['score'],
            'school_rank' => $row['school_rank'],
            'country_rank' => $row['country_rank'],
            'global_rank' => $row['global_rank'],
            'published' => 1
            ]
        );
        $result->participant->update(['status' => 'result computed']);
    }
}
