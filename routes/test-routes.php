<?php

/*
|--------------------------------------------------------------------------
| Test Routes
|--------------------------------------------------------------------------
| These routes are for test cases
|
*/

use App\Http\Controllers\TestingController;

Route::group(['prefix' => "testing"], function () {
    Route::get("/getNumberOfUploadedAnswersByLevelId/{level}", [TestingController::class, "getNumberOfParticipantsByLevelId"]);
    Route::post("/storeRemainingGroupCountriesForCompetitionId/{competition_id}", [TestingController::class, "storeRemainingGroupCountriesForCompetitionId"]);
    Route::post("/school/status", [TestingController::class, "setSchoolsToActive"]);
    Route::get("/fixIndianParticipants", [TestingController::class, "fixIndianParticipants"]);
    Route::post("/fixGlobalRank/{competition}", [TestingController::class, "fixGlobalRank"]);
    Route::get("/wrongGlobalRank/{competition}", [TestingController::class, "getWrongGlobalNumberCount"]);
});

