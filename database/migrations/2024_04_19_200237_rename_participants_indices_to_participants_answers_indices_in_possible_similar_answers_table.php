<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('possible_similar_answers', function (Blueprint $table) {
            $table->renameColumn('participants_indices', 'participants_answers_indices');
        });
    }

    public function down()
    {
        Schema::table('possible_similar_answers', function (Blueprint $table) {
            $table->renameColumn('participants_answers_indices', 'participants_indices');
        });
    }
};
