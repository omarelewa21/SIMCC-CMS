<?php

use App\Models\Competition;
use App\Models\IntegrityCase;
use App\Models\Participants;
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
        $competitionIds = [61, 66];
        $userId =
        Competition::whereIn('id', $competitionIds)->get()
            ->each(function ($competition) {
                $competition->participants()->where('participants.status', Participants::STATUS_CHEATING)->get()
                    ->each(function ($participant) {
                        IntegrityCase::firstOrCreate([
                            'participant_index' => $participant->index_no,
                            'mode' => 'system',
                        ]);
                    });
            });

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
