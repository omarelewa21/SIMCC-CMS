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
        Schema::table('competition_participants_results', function (Blueprint $table) {
            $table->decimal('points', $precision = 8, $scale = 2)->nullable()->change();
            $table->decimal('percentile', $precision = 5, $scale = 2)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('competition_participants_results', function (Blueprint $table) {
            $table->decimal('points', $precision = 8, $scale = 2)->change();
            $table->decimal('percentile', $precision = 5, $scale = 2)->default(0)->change();
        });
    }
};
