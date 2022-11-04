<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class Image extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::create('image', function (Blueprint $table) {
        // $table->bigIncrements ('id');
        // $table->morphs('imageable');
        // $table->longText('image_string');
        // $table->unsignedBigInteger('task_id');
        // $table->unsignedBigInteger('created_by_userid');
        // $table->date('created_at');
        // $table->date('updated_at');
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
