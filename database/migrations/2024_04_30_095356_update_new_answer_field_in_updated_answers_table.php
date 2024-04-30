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
        Schema::table('updated_answers', function (Blueprint $table) {
            $table->string('old_answer')->nullable()->change();
            $table->string('new_answer')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('updated_answers', function (Blueprint $table) {
            $table->string('old_answer')->nullable(false)->change();
            $table->string('new_answer')->nullable(false)->change();
        });
    }
};
