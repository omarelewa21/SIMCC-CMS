<?php

namespace App\Jobs;

use App\Models\CompetitionOrganization;
use App\Models\CompetitionParticipantsResults;
use App\Models\Participants;
use App\Services\ParticipantReportService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RecaculateShoolRankJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $changedSchoolsIds;
    protected $participantsWithNewSchoolId;

    public function __construct($changedSchoolsIds, $participantsWithNewSchoolId)
    {
        $this->changedSchoolsIds = $changedSchoolsIds;
        $this->participantsWithNewSchoolId = $participantsWithNewSchoolId;
    }

    public function handle()
    {
        $this->updateReportColumn();
        $this->recalculateSchoolRank();
    }

    public function recalculateSchoolRank()
    {
        $participants = Participants::whereIn('school_id', $this->changedSchoolsIds)->get();

        foreach ($participants as $participant) {
            $participantResults = CompetitionParticipantsResults::where('participant_index', $participant->index_no)
                ->orderBy('points', 'DESC')
                ->get();

            foreach ($participantResults as $index => $participantResult) {
                if ($index === 0) {
                    $participantResult->setAttribute('school_rank', $index + 1);
                } elseif ($participantResult->points === $participantResults[$index - 1]->points) {
                    $participantResult->setAttribute('school_rank', $participantResults[$index - 1]->school_rank);
                } else {
                    $participantResult->setAttribute('school_rank', $index + 1);
                }
                $participantResult->save();
            }
        }
    }

    public function updateReportColumn()
    {
        foreach ($this->participantsWithNewSchoolId as $participant) {
            try {
                $participantResult = CompetitionParticipantsResults::where('participant_index', $participant)
                    ->with('participant')
                    ->first();

                if ($participantResult) {
                    $reportService = new ParticipantReportService($participantResult->participant, $participantResult->competitionLevel);
                    $report = $reportService->getJsonReport();
                    $participantResult->report = $report;
                    $participantResult->save();
                }
            } catch (Exception $e) {
                continue;
            }
        }
    }
}
