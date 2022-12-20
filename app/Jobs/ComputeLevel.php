<?php

namespace App\Jobs;

use App\Custom\ComputeLevelCustom;
use App\Models\CompetitionLevels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComputeLevel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $level;

    public $timeout = 1000;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(CompetitionLevels $level)
    {
        $this->level = $level;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            (new ComputeLevelCustom($this->level))->computeResutlsForSingleLevel();
        } catch (\Exception $e) {
            $this->level->updateStatus(CompetitionLevels::STATUS_BUG_DETECTED, $e->getMessage());
        }
    }
}
