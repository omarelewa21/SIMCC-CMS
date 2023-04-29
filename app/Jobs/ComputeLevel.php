<?php

namespace App\Jobs;

use App\Custom\ComputeLevelCustom;
use App\Models\CompetitionLevels;
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
    protected Request|null $request = null;

    public $timeout = 5000;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(CompetitionLevels $level, Request|null $request = null)
    {
        $this->level = $level;
        $this->request = $request;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            if($this->request->hasAny(['score', 'groupRank', 'countryRank', 'schoolRank', 'awards', 'awardsRank', 'globalRank', 'reportColumn']))
                (new ComputeLevelCustom($this->level))->computeCustomFieldsForSingleLevelBasedOnRequest($this->request);
            else
                (new ComputeLevelCustom($this->level))->computeResutlsForSingleLevel();
        } catch (\Exception $e) {
            $this->level->updateStatus(CompetitionLevels::STATUS_BUG_DETECTED, $e);
        }
    }
}
