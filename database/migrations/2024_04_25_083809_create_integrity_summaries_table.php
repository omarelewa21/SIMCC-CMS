<?php

use App\Models\CheatingStatus;
use App\Models\IntegritySummary;
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
        Schema::create('integrity_summaries', function (Blueprint $table) {
            $table->id();
            $table->json('countries')->nullable();
            $table->json('grades')->nullable();
            $table->unsignedMediumInteger('total_cases_count')->default(0);
            $table->foreignId('run_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        CheatingStatus::where('status', 'Completed')
            ->get()
            ->each(function ($status) {
                DB::table('integrity_summaries')
                    ->insert([
                        'countries' => empty($status->original_countries) ? null : json_encode($status->original_countries),
                        'total_cases_count' => $status->total_cases_count,
                        'run_by' => $status->run_by,
                        'created_at' => $status->created_at,
                        'updated_at' => $status->updated_at,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('integrity_summaries');
    }
};
