<?php

use App\Models\ParticipantsAnswer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        ParticipantsAnswer::whereId('9126940')->update(['answer' => 'A']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        ParticipantsAnswer::whereId('9126940')->update(['answer' => 'C']);
    }
};
