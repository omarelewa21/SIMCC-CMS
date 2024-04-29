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
            $table->unsignedBigInteger('level_id')->nullable()->change();
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
            $table->unsignedBigInteger('level_id')->nullable(false)->change();
        });
    }
};
