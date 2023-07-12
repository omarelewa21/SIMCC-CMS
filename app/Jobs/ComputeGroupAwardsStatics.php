<?php

namespace App\Jobs;

use App\Models\AwardsStaticsResults;
use App\Models\AwardsStaticsStatus;
use App\Models\Competition;
use App\Models\CompetitionParticipantsResults;
use App\Models\Participants;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComputeGroupAwardsStatics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $competition;
    protected $competitionId;
    protected $group;
    protected $groupId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Competition $competition, $group)
    {
        $this->competition = $competition;
        $this->competitionId = $competition->id;
        $this->group = $group;
        $this->groupId = $group['id'];
        $this->updateJobProgress(0, 100, AwardsStaticsStatus::STATUS_NOT_STARTED);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $participantsQuery = $this->competition->participants()
                ->join('competition_participants_results', 'participants.index_no', '=', 'competition_participants_results.participant_index')
                ->where('competition_participants_results.group_id', $this->groupId);

            $totalParticipants = $participantsQuery
                ->groupBy('competition_participants_results.group_id', 'participants.grade')
                ->selectRaw('competition_participants_results.group_id, participants.grade, count(participants.id) as total_participants, group_concat(participants.id) as participant_ids')
                ->get();

            $awardPoints = [];
            $awardParticipants = [];

            $totalGrades = count($totalParticipants);

            foreach ($totalParticipants as $gradeData) {
                $this->group['grades'][] = [
                    'grade' => $gradeData->grade,
                    'totalParticipants' => $gradeData->total_participants,
                    'awards' => []
                ];

                $participantIds = explode(',', $gradeData->participant_ids);

                foreach ($participantIds as $participantId) {
                    $participant = Participants::find($participantId);
                    if ($participant) {
                        $result = CompetitionParticipantsResults::where('participant_index', $participant->index_no)->first();
                        $award = $result ? $result->award : '';
                        $points = $result ? $result->points : 0;
                        if ($award) {
                            if (!isset($awardPoints[$award][$gradeData->grade])) {
                                $awardPoints[$award][$gradeData->grade] = [];
                            }
                            if (!isset($awardParticipants[$award][$gradeData->grade])) {
                                $awardParticipants[$award][$gradeData->grade] = [];
                            }
                            $awardPoints[$award][$gradeData->grade][] = $points;
                            $awardParticipants[$award][$gradeData->grade][] = $participantId;
                        }
                    }
                }

                $this->group['totalParticipants'] += $gradeData->total_participants;

                // Update progress for each grade processed
                $this->updateJobProgress(count($this->group['grades']), $totalGrades, AwardsStaticsStatus::STATUS_IN_PROGRESS);
            }
            try {
                foreach ($awardPoints as $award => $gradePoints) {
                    foreach ($gradePoints as $grade => $points) {
                        $topPoints = max($points);
                        $leastPoints = min($points);
                        $participantsCount = count($awardParticipants[$award][$grade]);
                        $this->group['grades'][$grade - 1]['awards'][] = [
                            'award' => $award,
                            'topPoints' => $topPoints,
                            'leastPoints' => $leastPoints,
                            'participantsCount' => $participantsCount,
                            'participantsPercentage' => round(($participantsCount / $this->group['grades'][$grade - 1]['totalParticipants']) * 100, 2),
                            'awardsPercentage' => round(($participantsCount / $this->group['totalParticipants']) * 100, 2)
                        ];
                    }
                }

                $this->updateJobProgress(100, 100, AwardsStaticsStatus::STATUS_FINISHED);
                $this->updateAwardsStaticsResult($this->group);
            } catch (Exception $e) {
                $this->updateJobProgress(0, 0, AwardsStaticsStatus::STATUS_BUG_DETECTED, $e->getMessage());
            }
        } catch (\Exception $e) {
            $this->updateJobProgress(0, 0, AwardsStaticsStatus::STATUS_BUG_DETECTED, $e->getMessage());
        }
    }

    public function updateJobProgress($processedCount = null, $totalCount = null, $status = AwardsStaticsStatus::STATUS_IN_PROGRESS, $error = '')
    {
        $progress = 0;
        if ($processedCount) {
            $progress = ($totalCount > 0) ? round(($processedCount / $totalCount) * 100) : 0;
        }
        AwardsStaticsStatus::updateOrCreate(
            ['group_id' => $this->groupId],
            [
                'progress_percentage' => $progress,
                'status' => $status,
                'report' => $error
            ]
        );
    }

    public function updateAwardsStaticsResult($result)
    {
        try {
            AwardsStaticsResults::updateOrCreate(
                [
                    'group_id' => $this->groupId,
                    'competition_id' => $this->competitionId,
                ],
                [
                    'result' => $result
                ]
            );
        } catch (Exception $e) {
            $this->updateJobProgress(null, null, '', $e->getMessage());
        }
    }
}
