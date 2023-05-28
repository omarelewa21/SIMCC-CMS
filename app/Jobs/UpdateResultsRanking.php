<?php

namespace App\Jobs;

use App\Models\CompetitionParticipantsResults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateResultsRanking implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $participantIndex;
    protected $row;

    public $timeout = 5000;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($participantIndex, $row)
    {
        $this->participantIndex = $participantIndex;
        $this->row = $row;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        CompetitionParticipantsResults::where('participant_index', $this->participantIndex)
            ->update([
                'points'        => $this->row['updated_score'],
                'award'         => $this->row['updated_award'],
                'country_rank'  => $this->row['updated_country_rank'],
                'school_rank'   => $this->row['updated_school_rank'],
                'global_rank'   => $this->row['updated_global_rank'],
                'report'        => null,
            ]);
    }
}
