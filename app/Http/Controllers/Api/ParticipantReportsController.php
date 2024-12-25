<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\getParticipantListRequest;
use App\Models\Participants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

class ParticipantReportsController extends Controller
{
    public function listReports(Request $request)
    {
        try {
            $query = DB::table('participant_reports')
                ->leftJoin('competition', 'competition.id', '=', 'participant_reports.competition_id')
                ->leftJoin('all_countries', 'all_countries.id', '=', 'participant_reports.country_id')
                ->leftJoin('schools', 'schools.id', '=', 'participant_reports.school_id')
                ->leftJoin('users', 'users.id', '=', 'participant_reports.created_by')
                ->select(
                    'participant_reports.id',
                    'participant_reports.competition_id',
                    'participant_reports.country_id',
                    'participant_reports.progress',
                    'participant_reports.status',
                    'participant_reports.file',
                    'participant_reports.reports',
                    'participant_reports.downloads',
                    'participant_reports.errors',
                    'participant_reports.school_id',
                    'participant_reports.grade',
                    'participant_reports.created_by',
                    'participant_reports.created_at',
                    'participant_reports.updated_at',
                    'competition.name as competition_name',
                    'all_countries.display_name as country_name',
                    'schools.name as school_name',
                    'users.name as created_by_name'
                );

            if ($request->has('competition_id')) {
                $query->where('participant_reports.competition_id', $request->input('competition_id'));
            }

            if ($request->has('country_id')) {
                $query->where('participant_reports.country_id', $request->input('country_id'));
            }

            if ($request->has('school_id')) {
                $query->where('participant_reports.school_id', $request->input('school_id'));
            }

            $reports = $query->get()->map(function ($report) {
                if ($report->file) {
                    $report->download_url = url("/api/participant/reports/download/{$report->id}");
                } else {
                    $report->download_url = null;
                }
                if ($report->status == 'in_progress') {
                    $report->cancel_url = url("/api/participant/reports/cancel/{$report->id}");
                } else {
                    $report->cancel_url = null;
                }
                return $report;
            });

            return response()->json([
                'status' => 200,
                'message' => 'Reports fetched successfully',
                'data' => $reports,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while fetching the reports.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function generateReports(Request $request)
    {
        $user = auth()->user();
        $userCountryId = $user->country_id;
        $userOrganizationId = $user->organization_id;

        $query = DB::table('participants')
            ->leftJoin('all_countries', 'all_countries.id', '=', 'participants.country_id')
            ->leftJoin('schools', 'schools.id', '=', 'participants.school_id')
            ->leftJoin('schools as tuition_centre', 'tuition_centre.id', '=', 'participants.tuition_centre_id')
            ->leftJoin('competition_organization', 'competition_organization.id', '=', 'participants.competition_organization_id')
            ->leftJoin('competition', 'competition.id', '=', 'competition_organization.competition_id')
            ->when($request->filled('country_id'), function ($query) use ($request) {
                $query->where('participants.country_id', $request->country_id);
            })
            ->when($request->filled('school_id'), function ($query) use ($request) {
                $query->where('participants.school_id', $request->school_id);
            })
            ->when($request->filled('competition_id'), function ($query) use ($request) {
                $query->where('competition.id', $request->competition_id);
            })
            ->when($request->filled('grade'), function ($query) use ($request) {
                $query->where('participants.grade', $request->grade);
            })
            ->when($user->role === 2, function ($query) use ($userCountryId, $userOrganizationId) {
                $query->where('participants.country_id', $userCountryId)
                    ->where('participants.organization_id', $userOrganizationId);
            })
            ->select('participants.index_no', 'participants.name');

        $participantCount = $query->count();

        if ($participantCount > 1000) {
            return response()->json([
                'status' => 400,
                'message' => "The report you are trying to generate exceeds our system’s current capacity. Please adjust your selections and try again.",
                'count' => $participantCount,
            ], 400);
        }

        if ($participantCount === 0) {
            return response()->json([
                'status' => 404,
                'message' => 'No participants found with the specified filters.',
                'count' => $participantCount,
            ], 404);
        }

        if ($this->isReportBeingGenerated($request)) {
            return response()->json([
                'status' => 400,
                'message' => "A report with the same criteria is already being generated. Please wait for it to complete before attempting to generate another one.",
            ], 400);
        }

        $participants = $query->get()->toArray();
        $this->proceedGenerateReports($request, $participants);

        return response()->json([
            'status' => 200,
            'message' => "Your report is being generated. You can track its progress in the 'Reports' section of your profile. Feel free to close this window and continue with other tasks. We'll notify you when it's ready for download.",
            'count' => $participantCount,
        ]);
    }

    private function proceedGenerateReports($request, $participants)
    {
        $competitionId = $request->input('competition_id');
        $countryId = $request->input('country_id');
        $schoolId = $request->input('school_id');
        $grade = $request->input('grade');

        DB::table('participant_reports')->insert([
            'competition_id' => $competitionId,
            'country_id' => $countryId,
            'school_id' => $schoolId,
            'grade' => $grade,
            'participants' => json_encode($participants),
            'created_by' => auth()->id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function isReportBeingGenerated($request)
    {
        $competitionId = $request->input('competition_id');
        $countryId = $request->input('country_id');
        $schoolId = $request->input('school_id');
        $grade = $request->input('grade');

        return DB::table('participant_reports')
            ->where('competition_id', $competitionId)
            ->where('country_id', $countryId)
            ->where('school_id', $schoolId)
            ->where('grade', $grade)
            ->where('status', '!=', 'cancelled')
            ->exists();
    }

    public function downloadReports($report_id)
    {
        try {
            $report = DB::table('participant_reports')
                ->select('file', 'downloads')
                ->where('id', $report_id)
                ->first();

            if (!$report) {
                return response()->json([
                    'status' => 404,
                    'message' => 'File does not exist.',
                ], 404);
            }

            $filePath = 'performance_reports/' . $report->file;

            if (!Storage::exists($filePath)) {
                return response()->json([
                    'message' => 'File not found',
                ], 404);
            }

            DB::table('participant_reports')
                ->where('id', $report_id)
                ->increment('downloads', 1);

            return Response::download(Storage::path($filePath))->deleteFileAfterSend(false);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while processing the request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteReports($report_id)
    {
        try {
            $report = DB::table('participant_reports')
                ->where('id', $report_id)
                ->first();

            if (!$report) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Report not found.',
                ], 404);
            }

            $filePath = 'performance_reports/' . $report->file;

            if (!Storage::exists($filePath)) {
                return response()->json([
                    'status' => 404,
                    'message' => 'File does not exist.',
                ], 404);
            }

            Storage::delete($filePath);

            DB::table('participant_reports')->where('id', $report_id)->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Report and associated file deleted successfully.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while deleting the report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancelReports($report_id)
    {
        try {
            $report = DB::table('participant_reports')->where('id', $report_id)->first();

            if (!$report) {
                return response()->json([
                    'status' => 404,
                    'message' => 'Report not found.',
                ], 404);
            }

            if ($report->status === 'cancelled') {
                return response()->json([
                    'status' => 400,
                    'message' => 'Report is already cancelled.',
                ], 400);
            }

            if ($report->status !== 'in_progress') {
                return response()->json([
                    'status' => 400,
                    'message' => 'Report job is not in progress and cannot be canceled.',
                ], 400);
            }


            $job_id = $report->job_id;

            $job = DB::table('jobs')->where('id', $job_id)->first();
            if ($job) {
                DB::table('jobs')->where('id', $job_id)->delete();
            }

            if ($report->file) {
                $filePath = 'performance_reports/' . $report->file;
                if (Storage::exists($filePath)) {
                    Storage::delete($filePath);
                }
            }

            DB::table('participant_reports')
                ->where('id', $report_id)
                ->update([
                    'status' => 'cancelled',
                    'file' => null,
                    'updated_by' => auth()->id(),
                    'updated_at' => now(),
                ]);

            return response()->json([
                'status' => 200,
                'message' => 'Report job canceled successfully.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while canceling the report job.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
