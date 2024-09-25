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
        Schema::create('flag_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('competition')->onDelete('restrict');
            $table->foreignId('level_id')->constrained('competition_levels')->onDelete('restrict');
            $table->foreignId('group_id')->constrained('competition_marking_group')->onDelete('restrict');
            $table->string('type');
            $table->string('note')->nullable();
            $table->integer('status')->default(1);
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
        Schema::dropIfExists('flag_notifications');
    }
};
