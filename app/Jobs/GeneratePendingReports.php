<?php

namespace App\Jobs;

use App\Models\CompetitionParticipantsResults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\ReportDownloadStatus;
use App\Services\ParticipantReportService;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use PDF;


class GeneratePendingReports implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1000000;

    protected $report;
    protected $participants;
    protected $participantResults;
    protected $totalProgress;
    protected $progress;
    protected $jobId;
    protected $request;
    protected $user;

    public function __construct($report)
    {
        $this->report = $report;
    }

    public function handle()
    {
        try {
            $this->participants = json_decode($this->report->participants);
            if (!is_array($this->participants) && !is_object($this->participants)) {
                throw new \Exception('Invalid participants data. It must be an array or object.');
            }
            $this->participantResults = $this->getParticipantResults();
            $this->totalProgress = count($this->participantResults);
            $this->progress = 0;
            $this->jobId = $this->job->getJobId();
            $this->updateJobProgress($this->progress, $this->totalProgress, 'in_progress');
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
                } catch (Exception $e) {
                    $this->progress++;
                    continue;
                }

                $pdfFilename = sprintf('report_%s.pdf', $participantResult->participant['index_no'] . '_' . $cleanedName);
                $pdfPath = $pdfDirPath . '/' . $pdfFilename;
                Storage::put($pdfPath, $pdf->output());
                $this->progress++;
                $this->updateJobProgress($this->progress, $this->totalProgress, 'in_progress');
            }

            $zip = new ZipArchive;
            $zipFilename = sprintf('performance_reports_%s.zip', $time);
            $zipPath = 'performance_reports/' . $zipFilename;
            $zip->open(storage_path('app/' . $zipPath), ZipArchive::CREATE);

            foreach (Storage::files($pdfDirPath) as $file) {
                $zip->addFile(storage_path('app/' . $file), basename($file));
            }

            $zip->close();
            Storage::deleteDirectory($pdfDirPath);
            $this->updateJobProgress($this->progress, $this->totalProgress, 'completed', $zipFilename, null);
        } catch (Exception $e) {
            $this->updateJobProgress($this->progress, $this->totalProgress, 'failed', null, $e);
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

    public function updateJobProgress($processedCount, $totalCount, $status = 'in_progress', $file = null, $errors = null)
    {
        try {
            $progress = ($totalCount > 0) ? round(($processedCount / $totalCount) * 100) : 0;
            DB::table('participant_reports')
                ->updateOrInsert(
                    ['id' => $this->report->id],
                    [
                        'job_id' => $this->jobId,
                        'progress' => $progress,
                        'status' => $status,
                        'errors' => $errors,
                        'file' => $file,
                        'reports' => $processedCount !== null ? $processedCount : DB::raw('count')
                    ]
                );
        } catch (Exception $e) {
        }
    }
}
