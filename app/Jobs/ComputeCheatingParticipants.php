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

    protected $userId = null;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        protected Competition $competition,
        protected $qNumber=null,
        protected $percentage=null,
        protected $numberOFSameIncorrect=null,
        protected $countries=null,
        protected $forMapList = false

    ) {
        $this->userId = auth()->id();
    }

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
                $this->numberOFSameIncorrect ?? 5,
                $this->countries,
                $this->forMapList ?? false,
                userId: $this->userId
            ))->computeCheatingParticipants();
        }

        catch (\Exception $e) {
            $cheatingStatus = CheatingStatus::where([
                'competition_id'                    => $this->competition->id,
                'cheating_percentage'               => $this->percentage ?? 85,
                'number_of_same_incorrect_answers'  => $this->numberOFSameIncorrect ?? 5,
                'for_map_list'                      => $this->forMapList
            ])
            ->FilterByCountries($this->countries)
            ->first();

            if ($cheatingStatus) {
                $cheatingStatus->update([
                    'status'                => 'Failed',
                    'progress_percentage'   => 0,
                    'compute_error_message' => $e->getMessage()
                ]);
            } else {
                CheatingStatus::create([
                    'competition_id'                    => $this->competition->id,
                    'cheating_percentage'               => $this->percentage ?? 85,
                    'number_of_same_incorrect_answers'  => $this->numberOFSameIncorrect ?? 5,
                    'countries'                         => $this->countries ?? null,
                    'for_map_list'                      => $this->forMapList,
                    'status'                            => 'Failed',
                    'progress_percentage'               => 0,
                    'compute_error_message'             => $e->getMessage()
                ]);
            }
        }
    }
}
