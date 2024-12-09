<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\getParticipantListRequest;
use App\Models\Participants;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParticipantReports extends Controller
{
    public function generateReports(getParticipantListRequest $request)
    {
        $query = Participants::without('iac_status')->leftJoin('users as created_user', 'created_user.id', '=', 'participants.created_by_userid')
            ->leftJoin('users as modified_user', 'modified_user.id', '=', 'participants.last_modified_userid')
            ->leftJoin('all_countries', 'all_countries.id', '=', 'participants.country_id')
            ->leftJoin('schools', 'schools.id', '=', 'participants.school_id')
            ->leftJoin('schools as tuition_centre', 'tuition_centre.id', '=', 'participants.tuition_centre_id')
            ->leftJoin('competition_organization', 'competition_organization.id', '=', 'participants.competition_organization_id')
            ->leftJoin('competition', 'competition.id', '=', 'competition_organization.competition_id')
            ->filterList($request)
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
        $participants = array_map(function ($participant) {
            unset($participant['iac_status']);
            return $participant;
        }, $participants);

        DB::table('participant_reports')->insert([
            'competition_id' => $competitionId,
            'country_id' => $countryId,
            'school_id' => $schoolId,
            'grade' => $grade,
            'participants' => json_encode($participants),
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
            ->exists();
    }
}
