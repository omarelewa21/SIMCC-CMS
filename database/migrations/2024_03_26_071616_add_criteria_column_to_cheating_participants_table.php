<?php

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
        Schema::table('cheating_participants', function (Blueprint $table) {
            $table->decimal('criteria_cheating_percentage', 3, 0)->default(85)->after('competition_id');
            $table->unsignedTinyInteger('criteria_number_of_same_incorrect_answers')->default(5)->after('criteria_cheating_percentage');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('cheating_participants', function (Blueprint $table) {
            $table->dropColumn('criteria_cheating_percentage');
            $table->dropColumn('criteria_number_of_same_incorrect_answers');
        });
    }
};
