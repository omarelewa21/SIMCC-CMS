<?php

namespace App\Imports;

use App\Models\CompetitionParticipantsResults;
use App\Models\ParticipantsAnswer;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UploadUpdatedAnswersAndResults implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     */
    public function model(array $row)
    {
        $participantIndex = $row['index_number'];
        $this->updateParticipantsAnswers($participantIndex, $row);
        $this->updateParticipantsResults($participantIndex, $row);
    }

    private function updateParticipantsAnswers($participantIndex, $row)
    {
        $participantAnswers = ParticipantsAnswer::where('participant_index', $participantIndex)
            ->orderBy('task_id')->get();
        
        foreach($participantAnswers as $key => $answer) {
            if($answer->answer != $row["q" . $key+1]){
                $answer->answer = $row["q" . $key+1];
                $answer->score = $answer->getAnswerMark($answer->level_id);
                if($answer->score < 0)
                    $answer->score = 0;

                $answer->is_correct = $answer->getIsCorrectAnswer($answer->level_id);
                $answer->save();
            }
        }
    }

    private function updateParticipantsResults($participantIndex, $row)
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
