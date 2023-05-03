<?php

namespace App\Jobs;

use App\Models\CheatingStatus;
use App\Models\Competition;
use App\Services\ComputeCheatingParticipantsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ComputeCheatingParticipants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $competition;
    protected $qNumber;         // If cheating question number >= $qNumber, then the participant is considered as cheater
    protected $percentage;      // If cheating percentage >= $percentage, then the participant is considered as cheater

    public $timeout = 1000;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Competition $competition, $qNumber=null, $percentage=null)
    {
        $this->competition = $competition;
        $this->qNumber = $qNumber;
        $this->percentage = $percentage;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            (new ComputeCheatingParticipantsService($this->competition, $this->qNumber, $this->percentage ?? 95))->computeCheatingParticipants();

        } catch (\Exception $e) {
            CheatingStatus::updateOrCreate(
                ['competition_id' => $this->competition->id],
                [
                    'status' => 'Failed',
                    'progress_percentage' => 0,
                    'compute_error_message' => $e->getMessage()
                ]
            );
        }
    }
}
