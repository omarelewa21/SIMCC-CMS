<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        Schema::table('participants', function (Blueprint $table) {
            DB::statement("ALTER TABLE `participants` CHANGE `status` `status` ENUM('active', 'absent', 'result computed', 'iac') DEFAULT 'active';");
            $table->foreignId('eliminated_by')->nullable()->constrained('users')->onDelete('cascade');
            $table->timestamp('eliminated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('participants', function (Blueprint $table) {
            DB::statement("ALTER TABLE `participants` CHANGE `status` `status` ENUM('active', 'absent', 'result computed') DEFAULT 'active';");
            $table->dropForeign(['eliminated_by']);
            $table->dropColumn(['eliminated_by', 'eliminated_at']);
        });
    }
};
