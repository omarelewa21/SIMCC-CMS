<?php

namespace App\Jobs;

use App\Http\Controllers\api\ParticipantsController;
use App\Http\Requests\getParticipantListRequest;
use App\Models\CompetitionOrganization;
use App\Models\CompetitionParticipantsResults;
use App\Models\Participants;
use App\Models\ReportDownloadStatus;
use App\Services\ParticipantReportService;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use PDF;
use SebastianBergmann\Invoker\TimeoutException;
use Throwable;

class GeneratePerformanceReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $participants;
    protected $participantResults;
    protected $totalProgress;
    protected $progress;
    protected $jobId;
    protected $request;
    protected $user;
    protected $report;
    protected $participantController;
    public $timeout = 900;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function handle()
    {
        try {
            auth()->login($this->user);
            $this->progress = 0;
            $this->jobId = $this->job->getJobId();
            $this->updateJobProgress($this->progress, 100, ReportDownloadStatus::STATUS_In_PROGRESS);
            $this->participants = $this->getParticipants();

            if (count($this->participants) > 100) {
                $this->progress = 100;
                $this->totalProgress = 100;
                throw new Exception('The total count of reports exceeds the established limit of 100 reports.');
            }

            $this->participantResults = $this->getParticipantResults();
            $this->totalProgress = count($this->participantResults);

            if ($this->totalProgress < 1) {
                $this->progress = 100;
                $this->totalProgress = 100;
                throw new Exception('No results found for the selected participants');
            }

            $this->updateJobProgress($this->progress, $this->totalProgress, ReportDownloadStatus::STATUS_In_PROGRESS);
            $time = (new DateTime)->format('d_m_Y_H_i');
            $pdfDirname = sprintf('performance_reports_%s', $time);
            $pdfDirPath = 'performance_reports/' . $pdfDirname;
            Storage::makeDirectory($pdfDirPath);
            foreach ($this->participantResults as $participantResult) {
                try {
                    if (is_null($participantResult->report)) {
                        $__report = new ParticipantReportService($participantResult->participant, $participantResult->competitionLevel);
                        $report = $__report->getJsonReport();
                        $participantResult->report = $report;
                        $participantResult->save();
                    } else {
                        $report = $participantResult->report;
                    }
                    $report['general_data']['is_private'] = $participantResult->participant->tuition_centre_id ? true : false;
                    $cleanedName = preg_replace('/\s+/', '_', $participantResult->participant['name']);
                    $pdf = PDF::loadView('performance-report', [
                        'general_data'                  => $report['general_data'],
                        'performance_by_questions'      => $report['performance_by_questions'],
                        'performance_by_topics'         => $report['performance_by_topics'],
                        'grade_performance_analysis'    => $report['grade_performance_analysis'],
                        'analysis_by_questions'         => $report['analysis_by_questions']
                    ]);
                    $this->report[$participantResult->participant['index_no'] . '_' . $cleanedName] =  'success';
                } catch (Exception $e) {
                    $this->report[$participantResult->participant['index_no'] . '_' . $cleanedName] = 'failed: ' . $e->getMessage();
                    $this->progress++;
                    continue;
                }

                $pdfFilename = sprintf('report_%s.pdf', $participantResult->participant['index_no'] . '_' . $cleanedName);
                $pdfPath = $pdfDirPath . '/' . $pdfFilename;
                Storage::put($pdfPath, $pdf->output());
                $this->progress++;
                $this->updateJobProgress($this->progress, $this->totalProgress, ReportDownloadStatus::STATUS_In_PROGRESS, null, $this->report);
            }

            // Add the log file to the ZIP archive
            $zip = new ZipArchive;
            $zipFilename = sprintf('performance_reports_%s.zip', $time);
            $zipPath = 'performance_reports/' . $zipFilename;
            $zip->open(storage_path('app/' . $zipPath), ZipArchive::CREATE);
            // Add all PDF files to the ZIP archive
            foreach (Storage::files($pdfDirPath) as $file) {
                $zip->addFile(storage_path('app/' . $file), basename($file));
            }

            $zip->close();
            Storage::deleteDirectory($pdfDirPath);
            $this->updateJobProgress($this->progress, $this->totalProgress, ReportDownloadStatus::STATUS_COMPLETED, $zipFilename, $this->report);
        } catch (Exception | Throwable | TimeoutException | MaxAttemptsExceededException $e) {
            $this->report['public_error'] = $e->getMessage();
            $this->updateJobProgress($this->progress, $this->totalProgress, ReportDownloadStatus::STATUS_FAILED, null, $this->report);
        }
    }

    public function getParticipants()
    {
        try {
            $participantController = new ParticipantsController;
            $response = $participantController->list(new getParticipantListRequest);
            $data = json_decode($response->getContent());
            return $data->data->participantList->data;
        } catch (Exception $e) {
            throw new Exception('Failed to load participants ' . $e->getMessage());
        }
    }

    public function getParticipantResults()
    {
        $participantResults = [];
        foreach ($this->participants as $participant) {
            try {
                $result = CompetitionParticipantsResults::where('participant_index', $participant->index_no)
                    ->with('participant')->first();
                if ($result) {
                    $participantResults[] = $result->makeVisible('report');
                }
            } catch (Exception $e) {
                continue;
            }
        }
        return $participantResults;
    }


    public function updateJobProgress($processedCount, $totalCount, $status = ReportDownloadStatus::STATUS_In_PROGRESS, $file_path = null, $report = null)
    {
        try {
            $progress = ($totalCount > 0) ? round(($processedCount / $totalCount) * 100) : 0;
            ReportDownloadStatus::updateOrCreate(
                ['job_id' => $this->jobId],
                [
                    'progress_percentage' => $progress,
                    'status' => $status,
                    'report' => $report,
                    'file_path' => $file_path
                ]
            );
        } catch (Exception $e) {
        }
    }

    public function failed(Throwable $exception)
    {
        $errorMessage = $exception->getMessage();

        // Update the job progress and status as failed
        $this->updateJobProgress($this->progress, $this->totalProgress, ReportDownloadStatus::STATUS_FAILED, $errorMessage, $this->report);

        // Log the error message
        Log::error('Performance report generation failed: ' . $errorMessage);
    }
}
