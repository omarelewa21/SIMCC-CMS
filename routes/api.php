<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\ParticipantsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\CompetitionController;
use App\Http\Controllers\Api\DomainsTagsController;
use App\Http\Controllers\Api\HelperController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\TasksController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\TaskDifficultyController;
use App\Http\Controllers\Api\AssignDifficultyPointsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::post("login",[UserController::class,"login"]);

Route::group(["middleware" => ["cors","auth:sanctum","rolePermissions"]], function () {

    Route::group(["prefix" => "info"], function () {
        Route::get('countrylist', [HelperController::class, 'getCountryList'])->name('info.countryList');
        Route::get('competitionlist', [HelperController::class, 'getCompetitionList'])->name('info.competitionList');
        Route::get('roles', [HelperController::class, 'getRoleList'])->name('info.roles');
        Route::get('languages', [HelperController::class, 'getLanguagesList'])->name('info.languages');
        Route::get('levelCountyParticipantsList', [HelperController::class, 'levelCountyParticipantsList'])->name('info.levels.country.participants');

        Route::get('ConvertCompetitionOrganizationTable', [HelperController::class, 'ConvertCompetitionOrganizationTable'])->name('info.convert.competitionOrganization'); //run this first to add organization_id on previous competitonpartner table
        Route::get('CreateSchoolForOrganization', [HelperController::class, 'CreateSchoolForOrganization'])->name('info.convert.CreateSchoolForOrganization'); //run this to generate organization school based on the user country
        Route::get('setIndexCounterCountryTable', [HelperController::class, 'setIndexCounterCountryTable'])->name('info.convert.setIndexCounterCountryTable'); //store latest student index in country table
        Route::get('GenerateCertNum', [HelperController::class, 'GenerateCertNum'])->name('info.convert.GenerateCertNum'); //generate cert no.
    });

    Route::group(["prefix" => "notification"], function () {
        Route::get('', [NotificationController::class, 'notification.AllUnreadNotification']);
        Route::get('/{page}', [NotificationController::class, 'notification.UnreadNotification']);
    });

    Route::group(["prefix" => "tag"], function () {
        Route::post("",[DomainsTagsController::class,'create'])->name('tag.create');
        Route::patch("",[DomainsTagsController::class,'update'])->name('tag.update');
        Route::get("",[DomainsTagsController::class,'list'])->name('tag.list');
        Route::patch("/approve",[DomainsTagsController::class,"approve"])->name('tag.approve');
        Route::delete("",[DomainsTagsController::class,'delete'])->name('tag.delete');
    });

    Route::group(["prefix" => "organizations"], function () {
        Route::get("",[OrganizationController::class,'list'])->name('organization.list');
        Route::post("",[OrganizationController::class,'create'])->name('organization.create');
        Route::patch("",[OrganizationController::class,'update'])->name('organization.update');
        Route::delete("",[OrganizationController::class,'delete'])->name('organization.delete');
    });

    Route::group(["prefix" => "user"], function () {
        Route::get("/profile",[UserController::class,"profile"])->name('user.profile');
        Route::get("/logout",[UserController::class,"logout"])->name('user.logout');
        Route::get("",[UserController::class,"list"])->name('user.list');
        Route::post("",[UserController::class,"create"])->name('user.create');;
        Route::delete("",[UserController::class,"delete"])->name('user.delete');
        Route::patch("",[UserController::class,"update"])->name('user.update');
        Route::patch("/disable",[UserController::class,"disable"])->name('user.disable');
        Route::patch("/undisable",[UserController::class,"undisable"])->name('user.undisable');
    });

    Route::group(["prefix" => "school"], function () {
        Route::post("",[SchoolController::class,"create"])->name('school.create');
        Route::get("",[SchoolController::class,"list"])->name('school.list');
        Route::patch("",[SchoolController::class,"update"])->name('school.update');
        Route::delete("",[SchoolController::class,"delete"])->name('school.delete');
        Route::patch("/approve",[SchoolController::class,"approve"])->name('school.approve');
        Route::patch("/undelete",[SchoolController::class,"undelete"])->name('school.undeleted');
        Route::patch("/reject",[SchoolController::class,"reject"])->name('school.reject');
    });

    Route::group(["prefix" => "participant"], function () {
        Route::post("",[ParticipantsController::class,"create"])->name('participant.create');
        Route::get("",[ParticipantsController::class,"list"])->name('participant.list');
        Route::patch("",[ParticipantsController::class,"update"])->name('participant.update');
        Route::delete("",[ParticipantsController::class,"delete"])->name('participant.delete');
        Route::patch("/swapIndex",[ParticipantsController::class,"swapIndex"])->name('participant.swapIndex');
    });

//    Route::group(["prefix" => "tag"], function () {
//       Route::get("",[DomainsTagsController::class,'create'])->name('createTag');
//    });

    Route::group(["prefix" => "competition"],function () {
        Route::post("",[CompetitionController::class,"create"])->name('competition.create');
        Route::get("",[CompetitionController::class,"list"])->name('competition.list');
        Route::patch("",[CompetitionController::class,"update"])->name('competition.update');
        Route::delete("",[CompetitionController::class,"delete"])->name('competition.delete');
        Route::post("/upload_answers",[CompetitionController::class,"upload_answers"])->name('competition.upload_answers');
        Route::get("/difficultyandpoints",[AssignDifficultyPointsController::class,"list"])->name('competition.difficultyandpoints.list');
        Route::patch("/difficultyandpoints",[AssignDifficultyPointsController::class,"update"])->name('competition.difficultyandpoints.update');
        Route::post("/rounds",[CompetitionController::class,"addRounds"])->name('competition.rounds.add');
        Route::patch("/rounds",[CompetitionController::class,"editRounds"])->name('competition.rounds.edit');
        Route::delete("/rounds",[CompetitionController::class,"deleteRounds"])->name('competition.rounds.delete');
        Route::post("/organization",[CompetitionController::class,"addOrganization"])->name('competition.organization.add');
        Route::delete("/organization",[CompetitionController::class,"deleteOrganization"])->name('competition.organization.delete');
        Route::patch("/organization",[CompetitionController::class,"updateOrganizationDate"])->name('competition.');
        Route::post("/awards",[CompetitionController::class,"addRoundAwards"])->name('competition.round.award.add');
        Route::post("/awards",[CompetitionController::class,"addRoundAwards"])->name('competition.round.award.add');
        Route::patch("/awards",[CompetitionController::class,"editRoundAwards"])->name('competition.round.award.edit');
        Route::delete("/awards",[CompetitionController::class,"deleteRoundAwards"])->name('competition.round.award.delete');
        Route::post("/overall_awards",[CompetitionController::class,"addOverallAwards"])->name('competition.overall.award.add');
        Route::patch("/overall_awards",[CompetitionController::class,"editOverallAwards"])->name('competition.overall.award.edit');
        Route::delete("/overall_awards",[CompetitionController::class,"deleteOverallAwardsGroups"])->name('competition.overall.award.delete');
        Route::get("/report",[CompetitionController::class,"report"])->name('competition.report');
    });

    Route::group(["prefix" => "marking"],function () {
        Route::get("/preparation/{competition}",[App\Http\Controllers\api\MarkingController::class,"markingGroupsList"])->name('competition.marking.groups.list');
        Route::post("/preparation/{competition}",[App\Http\Controllers\api\MarkingController::class,"addMarkingGroups"])->name('competition.marking.groups.add');
        Route::get("/participants/country/{competition}",[\App\Http\Controllers\api\MarkingController::class,"getActiveParticipantsByCountryByGrade"])->name('competition.marking.byCountry.byGrade');
        Route::patch("/preparation",[CompetitionController::class,"editMarkingGroups"])->name('competition.marking.groups.edit');
        Route::get("/",[CompetitionController::class,"markingList"])->name('competition.marking.list');
        Route::patch("/",[CompetitionController::class,"changeComputeStatus"])->name('competition.group.status');
        Route::get("/compute",[CompetitionController::class,"computeGroupResults"])->name('competition.group.compute');
        Route::get("/edit",[CompetitionController::class,"editGroupComputedList"])->name('competition.computed.list');
        Route::patch("/edit",[CompetitionController::class,"editGroupComputedResult"])->name('competition.computed.edit');
    });

    Route::group(["prefix" => "tasks"], function () {
        Route::post("",[TasksController::class,"create"])->name('task.create');
        Route::get("",[TasksController::class,"list"])->name('task.list');
        Route::patch("settings",[TasksController::class,"update_settings"])->name('task.update.settings');
        Route::patch("recommendation",[TasksController::class,"update_recommendation"])->name('task.update.recommendation');
        Route::patch("content",[TasksController::class,"update_content"])->name('task.edit.content');
        Route::patch("answer",[TasksController::class,"update_answer"])->name('task.edit.answer');
    });

    Route::group(["prefix" => "collection"], function () {
        Route::get("",[CollectionController::class,"list"])->name('collection.list');
        Route::post("",[CollectionController::class,"create"])->name('collection.create');
        Route::delete("",[CollectionController::class,"delete"])->name('collection.delete');
        Route::patch("/settings",[CollectionController::class,"update_settings"])->name('collection.settings.update');
        Route::patch("/recommendations",[CollectionController::class,"update_recommendations"])->name('collection.recommendations.update');
        Route::post("/sections",[CollectionController::class,"add_sections"])->name('collection.sections.add');
        Route::patch("/sections",[CollectionController::class,"update_sections"])->name('collection.sections.update');
        Route::delete("/section",[CollectionController::class,"delete_section"])->name('collection.section.delete');
    });

    Route::group(['prefix' => "taskdifficultygroup"], function () {
        Route::post("",[TaskDifficultyController::class,"create"])->name('taskdifficulty.create');
        Route::get("",[TaskDifficultyController::class,"list"])->name('taskdifficulty.list');
        Route::patch("",[TaskDifficultyController::class,"update"])->name('taskdifficulty.update');
        Route::delete("difficulty",[TaskDifficultyController::class,"delete_difficulty"])->name('taskdifficulty.difficulty.delete');
        Route::delete("",[TaskDifficultyController::class,"delete"])->name('taskdifficulty.delete');
    });
});
