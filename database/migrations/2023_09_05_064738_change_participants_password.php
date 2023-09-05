<?php

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
        Participants::chunkById(1000, function ($participants) {
            foreach ($participants as $participant) {
                $participant->password = Participants::generatePassword();
                $participant->save();
            }
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
