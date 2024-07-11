<?php

namespace App\Http\Controllers\Api;

use App\Exports\AnswersReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Competition\ParticipantAnswersDeleteRequest;
use App\Http\Requests\getParticipantListRequest;
use App\Http\Requests\Participant\AnswerReportRequest;
use App\Models\Competition;
use App\Services\Competition\ParticipantAnswersListService;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ParticipantAnswersController extends Controller
{
    public function list(Competition $competition, getParticipantListRequest $request)
    {
        try {
            $participantAnswersService = new ParticipantAnswersListService($competition, $request);
            $participantAnswersList = $participantAnswersService->getList();

            return response()->json([
                "status"        => 200,
                "message"       => "Success",
                'competition'   => $competition->name,
                "filterOptions" => $participantAnswersService->getFilterOptions(),
                "headers"       => $participantAnswersService->getHeaders($participantAnswersList),
                "data"          => $participantAnswersList
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Internal Server Error {$e->getMessage()}",
                "error"     => strval($e)
            ], 500);
        }
    }

    public function delete(Competition $competition, ParticipantAnswersDeleteRequest $request)
    {
        try {
            $participantAnswersService = new ParticipantAnswersListService($competition, $request);
            $participantAnswersService->deleteParticipantsAnswers();
            return response()->json([
                "status"    => 200,
                "message"   => "Success",
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Internal Server Error {$e->getMessage()}",
                "error"     => strval($e)
            ], 500);
        }
    }

    public function answerReport(Competition $competition, AnswerReportRequest $request)
    {
        try {
            $reportName = ParticipantAnswersListService::getAnswersReportName($competition);

            if (Storage::disk('local')->exists($reportName)) {
                Storage::disk('local')->delete($reportName);
            }

            if (Excel::store(new AnswersReportExport($competition, $request), $reportName)) {
                $file = Storage::get($reportName);
                Storage::disk('local')->delete($reportName);
                $response = response()->make($file, 200);
                $response->header('Content-Type', 'application/' . pathinfo($reportName, PATHINFO_EXTENSION));
                $response->header('Content-Disposition', 'attachment; filename="' . $reportName . '"');
                return $response;
            }

            return response()->json([
                'status'    => 500,
                'message'   => 'Failed to generate cheating list'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                "status"    => 500,
                "message"   => "Internal Server Error {$e->getMessage()}",
                "error"     => strval($e)
            ], 500);
        }
    }

}
