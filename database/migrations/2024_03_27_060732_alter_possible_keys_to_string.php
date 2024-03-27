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
            $table->string('possible_keys', 255)->change();
        });

        Schema::table('possible_similar_answers', function (Blueprint $table) {
            $table->renameColumn('possible_keys', 'possible_key');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('possible_similar_answers', function (Blueprint $table) {
            $table->renameColumn('possible_key', 'possible_keys');
        });

        Schema::table('possible_similar_answers', function (Blueprint $table) {
            $table->json('possible_keys')->change();
        });
    }
};
