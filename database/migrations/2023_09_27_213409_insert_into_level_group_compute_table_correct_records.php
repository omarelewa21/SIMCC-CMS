<?php

use App\Models\CompetitionLevels;
use App\Models\LevelGroupCompute;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach(CompetitionLevels::with('rounds.competition.groups:id,competition_id')->get() as $level)
        {
            if($level->computing_status === CompetitionLevels::STATUS_FINISHED) {
                foreach($level->rounds->competition->groups as $group) {
                    LevelGroupCompute::updateOrCreate([
                        'group_id' => $group->id,
                        'level_id' => $level->id
                    ], [
                        'computing_status' => LevelGroupCompute::STATUS_FINISHED,
                        'compute_progress_percentage' => 100,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
};
