<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompetitionPartnerDate extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('competition_partner_date', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('competition_partner_id');
            $table->unsignedBigInteger('created_by_userid');
            $table->dateTime('competition_date');
            $table->timestamps();

            $table->foreign('competition_partner_id')->references('id')->on('competition_partner');
            $table->foreign('created_by_userid')->references('id')->on('users')->constrained();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('competition_partner_date');
    }
}
