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
        Schema::create('level_group_compute', function (Blueprint $table) {
            $table->foreignId('level_id')->constrained('competition_levels')->onDelete('cascade');
            $table->foreignId('group_id')->constrained('competition_marking_group')->onDelete('cascade');
            $table->primary(['level_id', 'group_id']);
            $table->enum('computing_status', ['Not Started', 'In Progress', 'Finished', 'Bug Detected'])->default('Not Started');
            $table->text('compute_error_message')->nullable();
            $table->unsignedTinyInteger('compute_progress_percentage')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('level_group_compute');
    }
};
