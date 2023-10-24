<?php

namespace App\Jobs;

use App\Models\CompetitionLevels;
use App\Services\ComputeLevelService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComputeLevel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected CompetitionLevels $level;
    protected array|null $request;

    public $timeout = 5000;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(CompetitionLevels $level, Request|null $request)
    {
        $this->level = $level;
        $this->request = $request->all();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            (new ComputeLevelService($this->level))->computeResutlsForSingleLevel();
            // if($this->request && !empty($this->request)){
            //     (new ComputeLevelService($this->level))->computeCustomFieldsForSingleLevelBasedOnRequest($this->request);

            // }
            // else{
            //     (new ComputeLevelService($this->level))->computeResutlsForSingleLevel();
            // }
        } catch (\Exception $e) {
            $this->level->updateStatus(CompetitionLevels::STATUS_BUG_DETECTED, $e);
        }
    }
}
