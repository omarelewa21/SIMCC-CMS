<?php

namespace App\Imports;

use App\Jobs\UpdateResultsRanking;
use App\Models\CompetitionParticipantsResults;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;


class UploadResultsRanking implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     */
    public function model(array $row)
    {
        $participantIndex = $row['index_number'];
        if($participantIndex !== null){
            if(CompetitionParticipantsResults::where('participant_index', $participantIndex)->exists()){
                // dispatch(new UpdateResultsRanking($participantIndex, $row));
                $this->updateParticipantsResults($participantIndex, $row);
            }
        }
    }

    public function updateParticipantsResults($participantIndex, $row)
    {
        CompetitionParticipantsResults::where('participant_index', $participantIndex)
            ->update([
                'points'        => $row['updated_score'],
                'award'         => $row['updated_award'],
                'country_rank'  => $row['updated_country_rank'],
                'school_rank'   => $row['updated_school_rank'],
                'global_rank'   => $row['updated_global_rank'],
                'report'        => null,
            ]);
    }
}
