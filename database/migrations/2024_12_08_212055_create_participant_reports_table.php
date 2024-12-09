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
        Schema::create('participant_reports', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('competition_id')->nullable();
            $table->foreign('competition_id')->references('id')->on('competition')->onDelete('set null');

            $table->unsignedSmallInteger('country_id')->nullable();
            $table->foreign('country_id')->references('id')->on('all_countries')->onDelete('set null');

            $table->unsignedMediumInteger('school_id')->nullable();
            $table->foreign('school_id')->references('id')->on('schools')->onDelete('set null');

            $table->string('grade')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            $table->integer('downloads')->default(0);
            $table->integer('reports')->default(0);
            $table->string('file')->nullable();

            $table->json('participants')->nullable();
            $table->string('job_id')->nullable();
            $table->json('errors')->nullable();

            $table->enum('status', ['pending', 'completed', 'failed', 'in_progress'])->default('pending');
            $table->integer('progress')->default(0);
            $table->boolean('notification_sent')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('participant_reports');
    }
};
