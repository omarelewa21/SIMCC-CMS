<?php

namespace App\Jobs;

use App\Custom\ParticipantReportService;
use App\Models\CompetitionParticipantsResults;
use App\Models\ReportDownloadStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

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
        try {
            foreach ($this->participants as $participant) {
                $participantResult = CompetitionParticipantsResults::where('participant_index', $participant['index_no'])
                    ->with('participant')->firstOrFail()->makeVisible('report');
                if (is_null($participantResult->report)) {
                    $__report = new ParticipantReportService($participantResult->participant, $participantResult->competitionLevel);
                    $report = $__report->getJsonReport();
                    $participantResult->report = $report;
                    $participantResult->save();
                } else {
                    $report = $participantResult->report;
                }

                $report['general_data']['is_private'] = $participantResult->participant->tuition_centre_id ? true : false;

                $pdf = Pdf::loadView('performance-report', [
                    'general_data'                  => $report['general_data'],
                    'performance_by_questions'      => $report['performance_by_questions'],
                    'performance_by_topics'         => $report['performance_by_topics'],
                    'grade_performance_analysis'    => $report['grade_performance_analysis'],
                    'analysis_by_questions'         => $report['analysis_by_questions']
                ]);

                $pdfFilename = sprintf('report_%s.pdf', $participantResult->participant['index_no'] . '_' . str_replace(' ', '_', $participantResult->participant['name']));
                $pdfPath = $pdfDirPath . '/' . $pdfFilename;
                Storage::put($pdfPath, $pdf->output());

                // Update the progress for this job
                $this->progress++;
                $this->updateJobProgress($this->progress, $this->totalProgress);
            }

            // Create a new ZipArchive object
            $zip = new ZipArchive;

            // Create a new ZIP archive in the storage directory
            $zipFilename = sprintf('performance_reports_%s.zip', $time);
            $zipPath = 'performance_reports/' . $zipFilename;

            // Open the ZIP archive for writing
            $zip->open(storage_path('app/' . $zipPath), ZipArchive::CREATE);

            // Add all PDF files to the ZIP archive
            foreach (Storage::files($pdfDirPath) as $file) {
                $zip->addFile(storage_path('app/' . $file), basename($file));
            }

            $zip->close();
            $zipFullPath = storage_path('app/' . $zipPath);
            Storage::deleteDirectory($pdfDirPath);
            $this->updateJobProgress($this->progress, $this->totalProgress, 'Completed', $zipFullPath);

            // return Response::download(storage_path('app/' . $zipPath))->deleteFileAfterSend(false);
        } catch (Exception $e) {
            $this->updateJobProgress($this->progress, $this->totalProgress, 'Failed');
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
