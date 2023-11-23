<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Competition\ParticipantAnswersDeleteRequest;
use App\Http\Requests\getParticipantListRequest;
use App\Models\Competition;
use App\Services\Competition\ParticipantAnswersListService;

class ParticipantAnswersController extends Controller
{
    public function list(Competition $competition, getParticipantListRequest $request)
    {
        try {
            $participantAnswersService = new ParticipantAnswersListService($competition, $request);
            return response()->json([
                "status"        => 200,
                "message"       => "Success",
                "filterOptions" => $participantAnswersService->getFilterOptions(),
                "data"          => $participantAnswersService->getList()
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
}
