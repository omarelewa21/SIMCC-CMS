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

    public $timeout = 5000;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected Competition $competition,
        protected $qNumber=null,
        protected $percentage=null,
        protected $number_of_incorrect_answers=null,
        protected $countries=null,
        protected $for_map_list = false

    ) {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            (new ComputeCheatingParticipantsService(
                $this->competition,
                $this->qNumber, $this->percentage ?? 85,
                $this->number_of_incorrect_answers ?? 5,
                $this->countries,
                $this->for_map_list ?? false
            ))->computeCheatingParticipants();
        }
        
        catch (\Exception $e) {
            CheatingStatus::updateOrCreate([
                'competition_id'                    => $this->competition->id,
                'cheating_percentage'               => $this->percentage ?? 85,
                'number_of_same_incorrect_answers'  => $this->number_of_incorrect_answers ?? 5,
                'countries'                         => $this->countries ?? null,
                'for_map_list'                      => $this->for_map_list
            ],
            [
                'status'                => 'Failed',
                'progress_percentage'   => 0,
                'compute_error_message' => $e->getMessage()
            ]);
        }
    }
}
