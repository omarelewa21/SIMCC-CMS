<?php

use App\Models\Grade;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Grade::insert([
            [
                'id'    => 16,
                'display_name' => 'ITE',
            ],
            [
                'id'    => 17,
                'display_name' => 'Polytechnic',
            ],
            [
                'id'    => 18,
                'display_name' => 'University',
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
        Grade::whereIn('display_name', ['ITE', 'Polytechnic', 'University'])->delete();
    }
};
