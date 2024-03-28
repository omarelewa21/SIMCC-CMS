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
        DB::statement("ALTER TABLE possible_answers MODIFY COLUMN status ENUM('waiting input', 'approved', 'declined') NOT NULL DEFAULT 'waiting input'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE possible_answers MODIFY COLUMN status TINYINT(1) NOT NULL DEFAULT '0'");
    }
};
