<?php

namespace App\Console\Commands;

use App\Custom\Marking;
use Illuminate\Console\Command;

class ComputeGroupsResults extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'group:compute';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for group status "computing" and compute that group';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $counter = 1;
        $timesRun = 29;

        while($counter <= $timesRun){
            $mark = new Marking();
            $results =  $mark->computingResults();
            $counter++;
            sleep(2);
            return $results;

        }
    }
}
