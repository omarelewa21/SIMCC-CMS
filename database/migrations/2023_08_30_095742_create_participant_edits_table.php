<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('participant_edits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('participant_id');
            $table->string('field_name');
            $table->string('old_value');
            $table->string('new_value');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('created_by_userid');
            $table->unsignedBigInteger('reject_reason');
            $table->unsignedBigInteger('approved_by_userid')->nullable();
            $table->timestamps();
            $table->foreign('participant_id')->references('id')->on('participants')->onDelete('cascade');
            $table->foreign('created_by_userid')->references('id')->on('users');
            $table->foreign('approved_by_userid')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('participant_edits');
    }
};
