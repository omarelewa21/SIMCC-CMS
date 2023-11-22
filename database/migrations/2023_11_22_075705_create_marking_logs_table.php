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
        Schema::create('marking_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained('competition_levels')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('competition_marking_group')->cascadeOnDelete();
            $table->foreignId('computed_by')->constrained('users');
            $table->date('computed_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('marking_logs');
    }
};
