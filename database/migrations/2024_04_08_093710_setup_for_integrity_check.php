<?php

use App\Models\CheatingParticipants;
use App\Models\CheatingStatus;
use App\Models\IntegrityCheckCompetitionCountries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::table('competition_cheat_compute_status', function (Blueprint $table) {
            $table->json('countries')->nullable();
        });

        DB::statement("ALTER TABLE `eliminated_cheating_participants` CHANGE `created_at` `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Date of elimination';");

        CheatingParticipants::truncate();
        CheatingStatus::truncate();
        IntegrityCheckCompetitionCountries::truncate();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('competition_cheat_compute_status', function (Blueprint $table) {
            $table->dropColumn('countries');
        });
    }
};
