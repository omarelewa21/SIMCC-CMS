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
        Schema::table('recommended_difficulty', function (Blueprint $table) {
            $table->string('difficulty')->nullable()->change();
            $table->smallInteger('grade')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('recommended_difficulty', function (Blueprint $table) {
            $table->string('difficulty')->nullable(false)->change();
            $table->smallInteger('grade')->nullable(false)->change();
        });
    }
};
