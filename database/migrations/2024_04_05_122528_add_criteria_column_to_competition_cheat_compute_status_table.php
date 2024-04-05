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
        Schema::table('competition_cheat_compute_status', function (Blueprint $table) {
            $table->decimal('cheating_percentage', 3, 0)->default(85);
            $table->unsignedTinyInteger('number_of_same_incorrect_answers')->default(5);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('competition_cheat_compute_status', function (Blueprint $table) {
            $table->dropColumn('cheating_percentage');
            $table->dropColumn('number_of_same_incorrect_answers');
        });
    }
};
