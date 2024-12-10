<?php

namespace App\Console\Commands;

use App\Jobs\GeneratePendingReports;
use Illuminate\Console\Command;
use App\Models\ParticipantReport;
use Illuminate\Support\Facades\DB;

class ProcessPendingReports extends Command
{
    protected $signature = 'reports:process-pending';
    protected $description = 'Process pending participant reports';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $pendingReports = DB::table('participant_reports')
                ->where('status', 'pending')
                ->orwhere('status', 'failed')
                ->get();

            foreach ($pendingReports as $report) {
                GeneratePendingReports::dispatch($report);
                DB::table('participant_reports')
                    ->where('id', $report->id)
                    ->update(['status' => 'in_progress']);
            }

            $this->info('Dispatched jobs for all pending reports.');
        } catch (\Exception $e) {
            $this->error('An error occurred: ' . $e->getMessage());
        }
    }
}
