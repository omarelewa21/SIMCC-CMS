<?php

namespace App\Jobs;

use App\Models\Competition;
use App\Models\CompetitionMarkingGroup;
use App\Models\LevelGroupCompute;
use App\Models\Participants;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class ProcessAnswerUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(protected Competition $competition, protected $request)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->updateParticipantStatus();
        $this->resetModerationStatus();
    }

    private function updateParticipantStatus()
    {
        Participants::whereIn('index_no', Arr::pluck($this->request['participants'], 'index_number'))
                ->doesntHave('integrityCases')
                ->update(['status' => Participants::STATUS_ACTIVE]);
    }

    private function resetModerationStatus()
    {
        Participants::whereIn('index_no', Arr::pluck($this->request['participants'], 'index_number'))
            ->select('country_id', 'grade')->groupBy('country_id', 'grade')->get()
            ->each(function ($record) {
                $levelId = $this->competition->levels()->whereJsonContains('competition_levels.grades', $record->grade)->value('competition_levels.id');
                if(is_null($levelId)) return;

                $groupId = CompetitionMarkingGroup::where('competition_id', $this->competition->id)
                    ->join('competition_marking_group_country', 'competition_marking_group_country.marking_group_id', 'competition_marking_group.id')
                    ->where('competition_marking_group_country.country_id', $record->country_id)
                    ->value('competition_marking_group.id');

                if(is_null($groupId)) return;

                LevelGroupCompute::where('level_id', $levelId)
                    ->where('group_id', $groupId)
                    ->update(['awards_moderated' => 0]);
            });
    }
}
