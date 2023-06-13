<?php

namespace App\Jobs;

use App\Custom\ParticipantReportService;
use App\Models\CompetitionParticipantsResults;
use App\Models\ReportDownloadStatus;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use PDF;


class GeneratePerformanceReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $participants;
    public $totalProgress;
    public $progress;
    public $jobId;
    public function __construct($participants)
    {
        $this->participants = $participants;
        $this->totalProgress = count($participants);
    }

    public function handle()
    {
        $this->progress = 0;
        $this->jobId = $this->job->getJobId();
        $this->updateJobProgress($this->progress, $this->totalProgress);
        $time = (new DateTime)->format('d_m_Y_H_i');
        $pdfDirname = sprintf('performance_reports_%s', $time);
        $pdfDirPath = 'performance_reports/' . $pdfDirname;
        Storage::makeDirectory($pdfDirPath);

        // Create a log file for the job
        $logFilename = sprintf('performance_reports_log_%s.txt', $time);
        $logPath = $pdfDirPath . '/' . $logFilename;
        $logFile = Storage::disk('local')->put($logPath, '');

        try {
            foreach ($this->participants as $participant) {

                try {
                    $participantResult = CompetitionParticipantsResults::where('participant_index', $participant['index_no'])
                        ->with('participant')->firstOrFail()->makeVisible('report');
                } catch (ModelNotFoundException $e) {
                    // Update the progress for this job
                    $this->progress++;
                    $this->updateJobProgress($this->progress, $this->totalProgress);
                    // Log the error and continue with the loop
                    $logMessage = sprintf('%s ------- Failed ------- %s', $participant['index_no'], 'No Results Found For This Participant');
                    Storage::append($logPath, $logMessage);
                    continue;
                }

                if ($participantResult) {
                    if (is_null($participantResult->report)) {
                        $__report = new ParticipantReportService($participantResult->participant, $participantResult->competitionLevel);
                        $report = $__report->getJsonReport();
                        $participantResult->report = $report;
                        $participantResult->save();
                    } else {
                        $report = $participantResult->report;
                    }

                    $report['general_data']['is_private'] = $participantResult->participant->tuition_centre_id ? true : false;

                    $pdf = PDF::loadView('performance-report', [
                        'general_data'                  => $report['general_data'],
                        'performance_by_questions'      => $report['performance_by_questions'],
                        'performance_by_topics'         => $report['performance_by_topics'],
                        'grade_performance_analysis'    => $report['grade_performance_analysis'],
                        'analysis_by_questions'         => $report['analysis_by_questions']
                    ]);

                    $pdfFilename = sprintf('report_%s.pdf', $participantResult->participant['index_no'] . '_' . str_replace(' ', '_', $participantResult->participant['name']));
                    $pdfPath = $pdfDirPath . '/' . $pdfFilename;
                    Storage::put($pdfPath, $pdf->output());

                    // Add a line to the log file for this participant
                    $logMessage = sprintf('%s ------- Completed', $participant['index_no']);
                    Storage::append($logPath, $logMessage);
                }

                // Update the progress for this job
                $this->progress++;
                $this->updateJobProgress($this->progress, $this->totalProgress);
            }

            // Add the log file to the ZIP archive
            $zip = new ZipArchive;
            $zipFilename = sprintf('performance_reports_%s.zip', $time);
            $zipPath = 'performance_reports/' . $zipFilename;
            $zip->open(storage_path('app/' . $zipPath), ZipArchive::CREATE);
            $zip->addFile(storage_path('app/' . $logPath), $logFilename);

            // Add all PDF files to the ZIP archive
            foreach (Storage::files($pdfDirPath) as $file) {
                $zip->addFile(storage_path('app/' . $file), basename($file));
            }

            $zip->close();
            // Storage::deleteDirectory($pdfDirPath);
            $this->updateJobProgress($this->progress, $this->totalProgress, 'Completed', $zipFilename);
        } catch (Exception $e) {
            $this->updateJobProgress($this->progress, $this->totalProgress, 'Failed' . $e->getMessage());
        }
    }

    public function updateJobProgress($processedCount, $totalCount, $status = 'Pending', $file_path = null)
    {
        $progress = ($totalCount > 0) ? round(($processedCount / $totalCount) * 100) : 0;
        ReportDownloadStatus::updateOrCreate(
            ['job_id' => $this->jobId],
            [
                'progress_percentage' => $progress,
                'status' => $status,
                'file_path' => $file_path
            ]
        );
    }
}
