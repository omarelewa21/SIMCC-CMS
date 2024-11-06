<?php

use App\Models\Grade;
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
        Grade::whereIn('display_name', ['ITE', 'Polytechnic', 'University'])->delete();
        Grade::insert([
            [
                'id'    => 16,
                'display_name' => 'ITE',
            ],
            [
                'id'    => 17,
                'display_name' => 'POLYTECHNIC',
            ],
            [
                'id'    => 18,
                'display_name' => 'UNIVERSITY',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Grade::whereIn('display_name', ['ITE', 'POLYTECHNIC', 'UNIVERSITY'])->delete();
    }
};
