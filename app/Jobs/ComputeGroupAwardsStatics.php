<?php

namespace App\Jobs;

use App\Models\AwardsStaticsResults;
use App\Models\AwardsStaticsStatus;
use App\Models\Competition;
use App\Models\Participant;
use App\Models\Participants;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComputeGroupAwardsStatics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The competition instance.
     *
     * @var \App\Models\Competition
     */
    protected $competition;

    /**
     * The ID of the competition.
     *
     * @var int
     */
    protected $competitionId;

    /**
     * The group array.
     *
     * @var array
     */
    protected $group;

    /**
     * The ID of the group.
     *
     * @var int
     */
    protected $groupId;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Competition  $competition
     * @param  array  $group
     * @return void
     */
    public function __construct(Competition $competition, array $group)
    {
        $this->competition = $competition;
        $this->competitionId = $competition->id;
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
            $participants = Participants::whereHas('country', function ($query) {
                $query->where('marking_group_id', $this->groupId);
            })->where('competition_id', $this->competitionId)->get();

            $grades = $participants->groupBy('grade')->map(function ($gradeParticipants) {
                $totalParticipants = $gradeParticipants->count();
                $awards = $gradeParticipants->mapToGroups(function ($participant) {
                    $result = $participant->result;
                    if ($result && $result->award) {
                        return [$result->award => $result->points];
                    }
                })->map(function ($awardPoints) use ($totalParticipants) {
                    $topPoints = $awardPoints->max();
                    $leastPoints = $awardPoints->min();
                    $participantsCount = $awardPoints->count();
                    return [
                        'topPoints' => $topPoints,
                        'leastPoints' => $leastPoints,
                        'participantsCount' => $participantsCount,
                        'participantsPercentage' => round(($participantsCount / $totalParticipants) * 100, 2),
                        'awardsPercentage' => round(($participantsCount / $totalParticipants) * 100, 2),
                    ];
                })->toArray();

                return [
                    'grade' => $gradeParticipants->first()->grade,
                    'totalParticipants' => $totalParticipants,
                    'awards' => $awards,
                ];
            })->values()->toArray();

            $totalParticipants = $participants->count();
            $this->group = [
                'id' => $this->groupId,
                'grades' => $grades,
                'totalParticipants' => $totalParticipants,
            ];

            $this->updateJobProgress(count($grades), count($grades), 'Completed');
            $this->updateAwardsStaticsResult($this->group);
        } catch (Exception $e) {
            $this->updateJobProgress(null, null, 'Failed', $e->getMessage());
        }
    }

    /**
     * Update the job progress.
     *
     * @param  int|null  $processedCount
     ** @param  int|null  $totalCount
     * @param  string  $status
     * @param  string  $error
     * @return void
     */
    public function updateJobProgress(?int $processedCount, ?int $totalCount, string $status, string $error = '')
    {
        $progress = $totalCount ? round(($processedCount / $totalCount) * 100) : 0;
        AwardsStaticsStatus::updateOrCreate(
            ['group_id' => $this->groupId],
            [
                'progress_percentage' => $progress,
                'status' => $status,
                'report' => $error,
            ]
        );
    }

    /**
     * Update the awards statistics result.
     *
     * @param  array  $result
     * @return void
     */
    public function updateAwardsStaticsResult(array $result)
    {
        try {
            AwardsStaticsResults::updateOrCreate(
                ['group_id' => $this->groupId, 'competition_id' => $this->competitionId],
                ['result' => $result]
            );
        } catch (Exception $e) {
            $this->updateJobProgress(null, null, '', $e->getMessage());
        }
    }
}
