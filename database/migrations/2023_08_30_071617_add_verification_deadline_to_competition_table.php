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
        Schema::table('competition', function (Blueprint $table) {
            $table->datetime('verification_deadline')->nullable();
        });
    }

    public function down()
    {
        Schema::table('competition', function (Blueprint $table) {
            $table->dropColumn('verification_deadline');
        });
    }
};
