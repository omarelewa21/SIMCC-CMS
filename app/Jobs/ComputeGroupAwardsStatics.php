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

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $competition;
    protected $competitionId;
    protected $group;
    protected $groupId;
    public function __construct(Competition $competition, $group)
    {
        $this->competition = $competition;
        $this->competitionId = $competition['id'];
        $this->group = $group;
        $this->groupId = $group['id'];
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
                ->join('competition_marking_group_country', 'participants.country_id', '=', 'competition_marking_group_country.country_id')
                ->where('competition_marking_group_country.marking_group_id', $this->groupId);

            $totalParticipants = $participantsQuery
                ->groupBy('competition_marking_group_country.marking_group_id', 'participants.grade')
                ->selectRaw('competition_marking_group_country.marking_group_id, participants.grade, count(participants.id) as total_participants, group_concat(participants.id) as participant_ids')
                ->get();

            $awardPoints = [];
            $awardParticipants = [];

            $totalGrades = count($totalParticipants);

            foreach ($totalParticipants as $gradeData) {
                $this->group['grades'][$gradeData->grade] = [
                    'grade' => $gradeData->grade,
                    'totalParticipants' => $gradeData->total_participants,
                    'awards' => []
                ];

                $participantIds = explode(',', $gradeData->participant_ids);

                foreach ($participantIds as $participantId) {
                    $participant = Participants::find($participantId);
                    $result = CompetitionParticipantsResults::where('participant_index', $participant->index_no)->first();
                    $award = $result ? $result->award : '';
                    $points = $result ? $result->points : 0;
                    if ($award) {
                        $awardPoints[$award][$gradeData->grade][] = $points;
                        $awardParticipants[$award][$gradeData->grade][] = $participantId;
                    }
                }

                $this->group['totalParticipants'] += $gradeData->total_participants;

                // Update progress for each grade processed
                $this->updateJobProgress(count($this->group['grades']), $totalGrades, 'Processing');
            }

            foreach ($awardPoints as $award => $gradePoints) {
                foreach ($gradePoints as $grade => $points) {
                    $topPoints = max($points);
                    $leastPoints = min($points);
                    $participantsCount = count($awardParticipants[$award][$grade]);
                    $this->group['grades'][$grade]['awards'][$award] = [
                        'topPoints' => $topPoints,
                        'leastPoints' => $leastPoints,
                        'participantsCount' => $participantsCount,
                        'participantsPercentage' => round(($participantsCount / $this->group['totalParticipants']) * 100, 2),
                        'awardsPercentage' => round(($participantsCount / $this->group['totalParticipants']) * 100, 2)
                    ];
                }
            }

            $this->updateJobProgress($totalGrades, $totalGrades, 'Completed');
            $this->updateAwardsStaticsResult($this->group);
        } catch (\Exception $e) {
            $this->updateJobProgress($totalGrades, $totalGrades, 'Failed', $e->getMessage());
        }
    }

    public function updateJobProgress($processedCount = null, $totalCount = null, $status = 'Pending', $error = '')
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
