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
    Route::get('/pdf-file', [TestingController::class, 'testPDF']);
});

