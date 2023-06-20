<?php

namespace App\Jobs;

use App\Custom\ParticipantReportService;
use App\Models\CompetitionParticipantsResults;
use App\Models\Participants;
use App\Models\ReportDownloadStatus;
use DateTime;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
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
    public function __construct($request)
    {
        $participants = $this->getParticipants($request);
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
        // $logFilename = sprintf('performance_reports_log_%s.txt', $time);
        // $logPath = $pdfDirPath . '/' . $logFilename;
        // $logFile = Storage::disk('local')->put($logPath, '');

        try {
            foreach ($this->participants as $participant) {

                try {
                    $participantResult = CompetitionParticipantsResults::where('participant_index', $participant)
                        ->with('participant')->firstOrFail()->makeVisible('report');
                } catch (ModelNotFoundException $e) {
                    // Update the progress for this job
                    $this->progress++;
                    $this->updateJobProgress($this->progress, $this->totalProgress);
                    // Log the error and continue with the loop
                    $logMessage = sprintf('%s ------- Failed ------- %s', $participant, 'No Results Found For This Participant');
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
                    $logMessage = sprintf('%s ------- Completed', $participant);
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
            // $zip->addFile(storage_path('app/' . $logPath), $logFilename);

            // Add all PDF files to the ZIP archive
            foreach (Storage::files($pdfDirPath) as $file) {
                $zip->addFile(storage_path('app/' . $file), basename($file));
            }

            $zip->close();
            Storage::deleteDirectory($pdfDirPath);
            $this->updateJobProgress($this->progress, $this->totalProgress, 'Completed', $zipFilename);
        } catch (Exception $e) {
            $this->updateJobProgress($this->progress, $this->totalProgress, 'Failed' . $e->getMessage());
        }
    }

    public function getParticipants($request)
    {
        $participantCollection = Participants::leftJoin('users as created_user', 'created_user.id', '=', 'participants.created_by_userid')
            ->leftJoin('users as modified_user', 'modified_user.id', '=', 'participants.last_modified_userid')
            ->leftJoin('all_countries', 'all_countries.id', '=', 'participants.country_id')
            ->leftJoin('schools', 'schools.id', '=', 'participants.school_id')
            ->leftJoin('schools as tuition_centre', 'tuition_centre.id', '=', 'participants.tuition_centre_id')
            ->leftJoin('competition_organization', 'competition_organization.id', '=', 'participants.competition_organization_id')
            ->leftJoin('organization', 'organization.id', '=', 'competition_organization.organization_id')
            ->leftJoin('competition', 'competition.id', '=', 'competition_organization.competition_id')
            ->leftJoin('competition_participants_results', 'competition_participants_results.participant_index', '=', 'participants.index_no')
            ->leftJoin('participant_answers', function ($join) {
                $join->on('participant_answers.participant_index', '=', 'participants.index_no');
            })
            ->select(
                'participants.*',
                'all_countries.display_name as country_name',
                DB::raw("CASE WHEN participants.tuition_centre_id IS NULL THEN 0 ELSE 1 END AS private"),
                'schools.id as school_id',
                'schools.name as school_name',
                'tuition_centre.id as tuition_centre_id',
                'tuition_centre.name as tuition_centre_name',
                'competition.id as competition_id',
                'competition.name as competition_name',
                'competition.alias as competition_alias',
                'organization.id as organization_id',
                'organization.name as organization_name',
                DB::raw("IF(competition_participants_results.published = 1, competition_participants_results.award, '-') AS award"),
                DB::raw('(COUNT(participant_answers.participant_index) > 0) as is_answers_uploaded')
            )
            ->filterList($request)
            ->groupBy('participants.id')
            ->get();
        $participants = $participantCollection->pluck('index_no')->toArray();
        return $participants;
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
