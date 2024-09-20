<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $grades = [
        [
            'id'    => 1,
            'display_name' => 'Grade 1 / Primary 1'
        ],
        [
            'id'    => 2,
            'display_name' => 'Grade 2 / Primary 2'
        ],
        [
            'id'    => 3,
            'display_name' => 'Grade 3 / Primary 3'
        ],
        [
            'id'    => 4,
            'display_name' => 'Grade 4 / Primary 4'
        ],
        [
            'id'    => 5,
            'display_name' => 'Grade 5 / Primary 5'
        ],
        [
            'id'    => 6,
            'display_name' => 'Grade 6 / Primary 6'
        ],
        [
            'id'    => 7,
            'display_name' => 'Grade 7 / Secondary 1'
        ],
        [
            'id'    => 8,
            'display_name' => 'Grade 8 / Secondary 2'
        ],
        [
            'id'    => 9,
            'display_name' => 'Grade 9 / Secondary 3'
        ],
        [
            'id'    => 10,
            'display_name' => 'Grade 10 / Secondary 4'
        ],
        [
            'id'    => 11,
            'display_name' => 'Grade 11 / Junior College 1'
        ],
        [
            'id'    => 12,
            'display_name' => 'Grade 12 / Junior College 2'
        ],
        [
            'id'    => 13,
            'display_name' => 'Grade 11 - 12 / Junior College 1 - 2'
        ],
        [
            'id'    => 14,
            'display_name' => 'K1'
        ],
        [
            'id'    => 15,
            'display_name' => 'K2'
        ],
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('grades');
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
        });

        DB::table('grades')->insert($this->grades);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('grades');
    }
};
