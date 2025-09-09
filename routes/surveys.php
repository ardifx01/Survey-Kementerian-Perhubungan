<?php

use App\Http\Controllers\SurveyController;
use App\Http\Controllers\SurveyRunController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

// Admin area
Route::middleware(['auth','verified','admin'])->group(function () {
    Route::get('/surveys', [SurveyController::class, 'index'])->name('surveys.index');
    Route::get('/surveys/create', [SurveyController::class, 'create'])->name('surveys.create');
    Route::post('/surveys', [SurveyController::class, 'store'])->name('surveys.store');
    Route::get('/surveys/{survey}/edit', [SurveyController::class, 'edit'])->name('surveys.edit');
    Route::put('/surveys/{survey}', [SurveyController::class, 'update'])->name('surveys.update');
    Route::post('/surveys/{survey}/publish', [SurveyController::class, 'publish'])->name('surveys.publish');
    Route::get('/surveys/{survey}/responses', [SurveyController::class, 'responses'])->name('surveys.responses');
    Route::get('/surveys/{survey}/responses/{response}', [SurveyController::class, 'responseShow'])->name('surveys.responses.show');
    Route::get('/surveys/{survey}/export/csv', [SurveyController::class, 'exportCsv'])->name('surveys.export.csv');
    Route::get('/surveys/{survey}/export/json', [SurveyController::class, 'exportJson'])->name('surveys.export.json');

    Route::post('/upload', [UploadController::class,'store'])->name('upload.store');
});

// Public runner
Route::get('/s/{slug}', [SurveyRunController::class, 'show'])->name('run.show');
Route::post('/s/{slug}', [SurveyRunController::class, 'submit'])->name('run.submit')->middleware('throttle:20,1');
