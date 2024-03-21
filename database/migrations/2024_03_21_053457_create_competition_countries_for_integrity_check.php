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
        Schema::create('competition_countries_for_integrity_check', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained('competition')->cascadeOnDelete();
            $table->unsignedSmallInteger('country_id');
            $table->foreign('country_id')->references('id')->on('all_countries')->cascadeOnDelete();
            $table->boolean('is_computed')->default(false);
            $table->boolean('is_confirmed')->default(false);
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('competition_countries_for_integrity_check');
    }
};
