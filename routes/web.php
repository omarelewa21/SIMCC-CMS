<?php

use App\Http\Controllers\TestingController;
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

Route::post('/upload-excel', [TestingController::class, "insertCsvIntoCompetitionParticipantResultsTable"])->name('uploadExcel');

Route::get('/upload-excel', function () {
    return view('upload-excel');
});