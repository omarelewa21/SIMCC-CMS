<?php

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
// Route::get('/test/report/{participant}', [\App\Http\Controllers\TestingController::class, 'report']);
// Route::get('/test/answer-report/{competition}', [\App\Http\Controllers\TestingController::class, 'answerReport'])->name('test.answer-report');
// Route::post('/test/answer-report/{competition}', [\App\Http\Controllers\TestingController::class, 'answerReportPost'])->name('test.answer-report.post');
