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
            // Change the collation of the possible_key to case-sensitive
            $table->string('possible_key')->collation('utf8mb4_bin')->change();
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
            $table->string('possible_key')->collation('utf8mb4_unicode_ci')->change();
        });
    }
};
