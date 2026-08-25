<?php

use App\Http\Controllers\Assessment\AssessmentController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:assessment')->group(function () {
    Route::get('assessment', [AssessmentController::class, 'show'])->name('assessment.show');
    Route::post('assessment/answer', [AssessmentController::class, 'answer'])->name('assessment.answer');
    Route::post('assessment/contact', [AssessmentController::class, 'contact'])->name('assessment.contact');
    Route::post('assessment/back', [AssessmentController::class, 'back'])->name('assessment.back');
    Route::post('assessment/submit', [AssessmentController::class, 'submit'])->name('assessment.submit');
    Route::get('assessment/result', [AssessmentController::class, 'result'])->name('assessment.result');
});
