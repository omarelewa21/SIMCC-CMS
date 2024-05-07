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
        Schema::table('possible_similar_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('level_id')->nullable()->after('task_id');
            $table->mediumText('participants_indices')->nullable()->after('possible_key');
            $table->foreign('level_id')
                ->references('id')->on('competition_levels')
                ->onDelete('set null');
        });
    }
    public function down()
    {
        Schema::table('possible_similar_answers', function (Blueprint $table) {
            $table->dropForeign(['level_id']);
            $table->dropColumn('level_id');
            $table->dropColumn('participants_indices');
        });
    }
};
