<?php

use App\Http\Controllers\Api\MarkingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    abort(404);
    return view('welcome');
});

Route::post('/upload-answers-and-results', [MarkingController::class, "uploadUpdatedAnswersAndResultsCSV"])->name('upload-answer-and-results-csv');

Route::get('/upload-answers-and-results', function () {
    return view('upload-updated-answers-and-results');
});

Route::post('/upload-results', [MarkingController::class, "updateResultsRanking"])->name('upload-results-csv');

Route::get('/upload-results', function () {
    return view('upload-results');
});