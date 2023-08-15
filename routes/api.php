<?php

use App\Helpers\CheatingListHelper;
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
use App\Http\Controllers\Api\MarkingController;

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


Route::post("login", [UserController::class, "login"]);
Route::get("participant/report/by-certificate", [ParticipantsController::class, "performanceReportWithIndexAndCertificate"])->name('participant.report.byCertificate');
Route::get('/cheating-csv/{competition}', [CheatingListHelper::class, 'getCheatingCSVFile'])->name('cheating-csv');

Route::group(["middleware" => ["cors", "auth:sanctum", "rolePermissions"]], function () {

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
        Route::post("", [DomainsTagsController::class, 'create'])->name('tag.create');
        Route::patch("", [DomainsTagsController::class, 'update'])->name('tag.update');
        Route::get("", [DomainsTagsController::class, 'list'])->name('tag.list');
        Route::patch("/approve", [DomainsTagsController::class, "approve"])->name('tag.approve');
        Route::delete("", [DomainsTagsController::class, 'delete'])->name('tag.delete');
    });

    Route::group(["prefix" => "organizations"], function () {
        Route::get("", [OrganizationController::class, 'list'])->name('organization.list');
        Route::post("", [OrganizationController::class, 'create'])->name('organization.create');
        Route::patch("", [OrganizationController::class, 'update'])->name('organization.update');
        Route::delete("", [OrganizationController::class, 'delete'])->name('organization.delete');
    });

    Route::group(["prefix" => "user"], function () {
        Route::get("/profile", [UserController::class, "profile"])->name('user.profile');
        Route::get("/logout", [UserController::class, "logout"])->name('user.logout');
        Route::get("", [UserController::class, "list"])->name('user.list');
        Route::post("", [UserController::class, "create"])->name('user.create');;
        Route::delete("", [UserController::class, "delete"])->name('user.delete');
        Route::patch("", [UserController::class, "update"])->name('user.update');
        Route::patch("/disable", [UserController::class, "disable"])->name('user.disable');
        Route::patch("/undisable", [UserController::class, "undisable"])->name('user.undisable');
    });

    Route::group(["prefix" => "school"], function () {
        Route::post("", [SchoolController::class, "create"])->name('school.create');
        Route::get("", [SchoolController::class, "list"])->name('school.list');
        Route::patch("", [SchoolController::class, "update"])->name('school.update');
        Route::delete("", [SchoolController::class, "delete"])->name('school.delete');
        Route::patch("/approve", [SchoolController::class, "approve"])->name('school.approve');
        Route::patch("/undelete", [SchoolController::class, "undelete"])->name('school.undeleted');
        Route::patch("/reject", [SchoolController::class, "reject"])->name('school.reject');
    });

    Route::group(["prefix" => "participant"], function () {
        Route::post("", [ParticipantsController::class, "create"])->name('participant.create');
        Route::get("", [ParticipantsController::class, "list"])->name('participant.list');
        Route::patch("", [ParticipantsController::class, "update"])->name('participant.update');
        Route::delete("", [ParticipantsController::class, "delete"])->name('participant.delete');
        Route::patch("/swapIndex", [ParticipantsController::class, "swapIndex"])->name('participant.swapIndex');
        Route::post("/compute/cheaters/eliminate", [ParticipantsController::class, "eliminateParticipantsFromCompute"])->name('participant.compute.cheaters.eliminate');
        Route::delete("/compute/cheaters/eliminate", [ParticipantsController::class, "deleteEliminatedParticipantsFromCompute"])->name('participant.compute.cheaters.eliminate.delete');
    });

    Route::group(["prefix" => "competition"], function () {
        Route::post("", [CompetitionController::class, "create"])->name('competition.create');
        Route::get("", [CompetitionController::class, "list"])->name('competition.list');
        Route::get("/{competition}", [CompetitionController::class, "show"])->name('competition.show');
        Route::patch("/edit-settings/{competition}", [CompetitionController::class, "update"])->name('competition.update');
        Route::delete("", [CompetitionController::class, "delete"])->name('competition.delete');
        Route::post("/upload_answers", [CompetitionController::class, "upload_answers"])->name('competition.upload_answers');
        Route::get("/difficultyandpoints/list", [AssignDifficultyPointsController::class, "list"])->name('competition.difficultyandpoints.list');
        Route::patch("/difficultyandpoints", [AssignDifficultyPointsController::class, "update"])->name('competition.difficultyandpoints.update');
        Route::post("/rounds", [CompetitionController::class, "addRoundsRoute"])->name('competition.rounds.add');
        Route::patch("/rounds", [CompetitionController::class, "editRounds"])->name('competition.rounds.edit');
        Route::delete("/rounds", [CompetitionController::class, "deleteRounds"])->name('competition.rounds.delete');
        Route::post("/organization", [CompetitionController::class, "addOrganizationRoute"])->name('competition.organization.add');
        Route::delete("/organization", [CompetitionController::class, "deleteOrganization"])->name('competition.organization.delete');
        Route::patch("/organization", [CompetitionController::class, "updateOrganizationDate"])->name('competition.organization.update.date');
        Route::get("/awards/{round}", [CompetitionController::class, "getRoundAwards"])->name('competition.round.award.add');
        Route::post("/awards", [CompetitionController::class, "addRoundAwards"])->name('competition.round.award.add');
        Route::patch("/awards", [CompetitionController::class, "editRoundAwards"])->name('competition.round.award.edit');
        Route::delete("/awards", [CompetitionController::class, "deleteRoundAwards"])->name('competition.round.award.delete');
        Route::post("/overall_awards", [CompetitionController::class, "addOverallAwards"])->name('competition.overall.award.add');
        Route::patch("/overall_awards", [CompetitionController::class, "editOverallAwards"])->name('competition.overall.award.edit');
        Route::delete("/overall_awards", [CompetitionController::class, "deleteOverallAwardsGroups"])->name('competition.overall.award.delete');
        Route::get("/{competition}/report", [CompetitionController::class, "report"])->name('competition.report');
        Route::get("/{competition}/countries", [CompetitionController::class, "competitionCountries"])->name('competition.countries');
        Route::get("/compute/cheaters/{competition}", [CompetitionController::class, "getcheatingParticipants"])->name('competition.compute.cheaters');
        Route::get("/compute/cheaters/group/{group_id}", [CompetitionController::class, "getcheatingParticipantsByGroup"])->name('competition.compute.cheaters.group');
    });

    Route::group(["prefix" => "marking"], function () {
        Route::get("/preparation/{competition}", [MarkingController::class, "markingGroupsList"])->name('competition.marking.groups.list');
        Route::post("/preparation/{competition}", [MarkingController::class, "addMarkingGroups"])->name('competition.marking.groups.add');
        Route::patch("/preparation/{group}", [MarkingController::class, "editMarkingGroup"])->name('competition.marking.groups.edit');
        Route::delete("/preparation/{group}", [MarkingController::class, "deleteMarkingGroup"])->name('competition.marking.groups.delete');
        Route::post("/participants/country/{competition}", [MarkingController::class, "getActiveParticipantsByCountryByGrade"])->name('competition.marking.byCountry.byGrade');
        Route::get("/{competition}", [MarkingController::class, "markingList"])->name('competition.marking.list')->middleware('cacheResponse:604800');
        // Route::get("/{competition}",[MarkingController::class,"markingList"])->name('competition.marking.list');
        Route::post("/compute/level/{level}", [MarkingController::class, "computeResultsForSingleLevel"])->name('competition.marking.compute.level');
        Route::post("/compute/{competition}", [MarkingController::class, "computeCompetitionResults"])->name('competition.marking.compute.competition');
        Route::get("/moderate/{level}/{group}", [MarkingController::class, "moderateList"])->name('competition.marking.moderate.list');
        Route::patch("/moderate/{level}", [MarkingController::class, "editParticipantAward"])->name('competition.marking.moderate.edit');
        Route::get("awards/stats/{group}", [MarkingController::class, "getAwardsStats"])->name('competition.marking.awards.stats');
    });

    Route::group(["prefix" => "tasks"], function () {
        Route::post("", [TasksController::class, "create"])->name('task.create');
        Route::get("", [TasksController::class, "list"])->name('task.list');
        Route::patch("settings", [TasksController::class, "update_settings"])->name('task.update.settings');
        Route::patch("recommendation", [TasksController::class, "update_recommendation"])->name('task.update.recommendation');
        Route::patch("content", [TasksController::class, "update_content"])->name('task.edit.content');
        Route::patch("answer", [TasksController::class, "update_answer"])->name('task.edit.answer');
        Route::delete("", [TasksController::class, "delete"])->name('task.delete');
        Route::post("duplicate/{task}", [TasksController::class, "duplicate"])->name('task.duplicate');
        Route::post("verify/{task}", [TasksController::class, "verify"])->name('task.verify');
    });

    Route::group(["prefix" => "collection"], function () {
        Route::get("", [CollectionController::class, "list"])->name('collection.list');
        Route::post("", [CollectionController::class, "create"])->name('collection.create');
        Route::delete("", [CollectionController::class, "delete"])->name('collection.delete');
        Route::patch("/settings", [CollectionController::class, "update_settings"])->name('collection.settings.update');
        Route::patch("/recommendations", [CollectionController::class, "update_recommendations"])->name('collection.recommendations.update');
        Route::post("/sections", [CollectionController::class, "add_sections"])->name('collection.sections.add');
        Route::patch("/sections", [CollectionController::class, "update_sections"])->name('collection.sections.update');
        Route::delete("/section", [CollectionController::class, "delete_section"])->name('collection.section.delete');
        Route::post("duplicate/{collection}", [CollectionController::class, "duplicate"])->name('collection.duplicate');
        Route::post("verify/{collection}", [CollectionController::class, "verify"])->name('collection.verify');
        Route::get("difficultyandpoints/overview", [CollectionController::class, "difficultyAndPointsOverview"])->name('collection.difficultyAndPointsOverview');
        Route::post("/difficultyandpoints/verify", [AssignDifficultyPointsController::class, "verify"])->name('collection.difficultyandpoints.verify');
    });

    Route::group(['prefix' => "taskdifficultygroup"], function () {
        Route::post("", [TaskDifficultyController::class, "create"])->name('taskdifficulty.create');
        Route::get("", [TaskDifficultyController::class, "list"])->name('taskdifficulty.list');
        Route::patch("", [TaskDifficultyController::class, "update"])->name('taskdifficulty.update');
        Route::delete("difficulty", [TaskDifficultyController::class, "delete_difficulty"])->name('taskdifficulty.difficulty.delete');
        Route::delete("", [TaskDifficultyController::class, "delete"])->name('taskdifficulty.delete');
    });

    include 'test-routes.php';
});
