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
    protected $number_of_incorrect_answers; // If number of incorrect answers > $number_of_incorrect_answers, then the participant is considered as cheater
    protected $countries;       // If countries is not null, then only compute the cheating participants from these countries

    public $timeout = 5000;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        Competition $competition,
        $qNumber=null,
        $percentage=null,
        $number_of_incorrect_answers=null,
        $countries=null
    )
    {
        $this->competition = $competition;
        $this->qNumber = $qNumber;
        $this->percentage = $percentage;
        $this->number_of_incorrect_answers = $number_of_incorrect_answers;
        $this->countries = $countries;
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
                $this->number_of_incorrect_answers ?? 5,
                $this->countries
            )
            )->computeCheatingParticipants();

        } catch (\Exception $e) {
            CheatingStatus::updateOrCreate(
                [
                    'competition_id'    => $this->competition->id,
                    'cheating_percentage'        => $this->percentage ?? 85,
                    'number_of_same_incorrect_answers' => $this->number_of_incorrect_answers ?? 5,
                ],
                [
                    'status' => 'Failed',
                    'progress_percentage' => 0,
                    'compute_error_message' => $e->getMessage()
                ]
            );
        }
    }
}
