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
    Route::get("/testCompetitionReportData/{competition}", [TestingController::class, "testCompetitionReportData"]);
    Route::post("/storeRemainingGroupCountriesForCompetitionId/{competition_id}", [TestingController::class, "storeRemainingGroupCountriesForCompetitionId"]);
});

