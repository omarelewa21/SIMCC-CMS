<?php

namespace App\Jobs;

use App\Models\CompetitionLevels;
use App\Models\CompetitionMarkingGroup;
use App\Models\LevelGroupCompute;
use App\Services\ComputeLevelGroupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComputeLevelGroupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 5000;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected CompetitionLevels $level,
        protected CompetitionMarkingGroup $group,
        protected array $request)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            (new ComputeLevelGroupService($this->level, $this->group))
                ->computeResutlsForGroupLevel($this->request);

        } catch (\Exception $e) {
            $this->group->levelGroupCompute($this->level->id)
                ->firstOrCreate(
                    ['level_id' => $this->level->id, 'group_id' => $this->group->id],
                    ['computing_status' => LevelGroupCompute::STATUS_BUG_DETECTED, 'compute_error_message' => $e]
                );
        }
        
    }
}
