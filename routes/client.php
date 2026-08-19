<?php

use App\Http\Controllers\Client\Auth\LoginController;
use App\Http\Controllers\Client\Auth\MagicLinkController;
use App\Http\Controllers\Client\Auth\RegisterController;
use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\DocumentController;
use App\Http\Controllers\Client\DraftController;
use App\Http\Controllers\Client\QuestionnaireController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Client area
|--------------------------------------------------------------------------
| EVERY route here sits behind `client.enabled`, which 404s while
| client_portal_enabled is false. The area is fully built but commercially
| gated: nothing is reachable until Summit approves that phase in writing.
*/

Route::prefix('client')->name('client.')->middleware('client.enabled')->group(function () {

    Route::middleware('guest:web')->group(function () {
        Route::get('register', [RegisterController::class, 'create'])->name('register');
        Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:login')->name('register.store');

        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');

        // Presented as an equal option to the password, not a fallback.
        Route::get('magic-link', [MagicLinkController::class, 'create'])->name('magic-link');
        Route::post('magic-link', [MagicLinkController::class, 'send'])->middleware('throttle:magic-link')->name('magic-link.send');
        Route::get('magic-link/sent', [MagicLinkController::class, 'sent'])->name('magic-link.sent');

        Route::get('forgot-password', [LoginController::class, 'requestReset'])->name('password.request');
        Route::post('forgot-password', [LoginController::class, 'sendReset'])->middleware('throttle:login')->name('password.email');
        Route::get('reset-password/{token}', [LoginController::class, 'resetForm'])->name('password.reset');
        Route::post('reset-password', [LoginController::class, 'reset'])->middleware('throttle:login')->name('password.update');
    });

    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('auth:web')->group(function () {

        // Email verification.
        Route::get('verify-email', fn () => Inertia::render('Client/Auth/VerifyEmail'))->name('verification.notice');
        Route::get('verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
            $request->fulfill();

            return redirect()->route('client.dashboard')->with('success', 'Your email address is confirmed.');
        })->middleware('signed')->name('verification.verify');
        Route::post('verify-email/resend', function (Request $request) {
            $request->user()->sendEmailVerificationNotification();

            return back()->with('success', 'A new verification email is on its way.');
        })->middleware('throttle:6,1')->name('verification.send');

        Route::get('/', DashboardController::class)->name('dashboard');

        // Detailed questionnaire.
        Route::get('cases/{case}/questionnaire', [QuestionnaireController::class, 'show'])->name('questionnaire');
        Route::post('cases/{case}/questionnaire/answer', [QuestionnaireController::class, 'answer'])->name('questionnaire.answer');
        Route::post('cases/{case}/questionnaire/back', [QuestionnaireController::class, 'back'])->name('questionnaire.back');
        Route::post('cases/{case}/questionnaire/submit', [QuestionnaireController::class, 'submit'])->name('questionnaire.submit');

        // Documents — private disk, signed expiring URLs only.
        Route::get('cases/{case}/documents', [DocumentController::class, 'index'])->name('documents');
        Route::post('cases/{case}/documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');

        // Drafts.
        Route::get('cases/{case}/drafts', [DraftController::class, 'show'])->name('drafts');
        Route::post('drafts/{draft}/amendments', [DraftController::class, 'requestAmendment'])->name('drafts.amend');
        Route::post('drafts/{draft}/approve', [DraftController::class, 'approve'])->name('drafts.approve');
    });

    // Signed download routes: the signature IS the authorisation, so they sit
    // outside the auth group and expire on their own.
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('drafts/{draft}/download', [DraftController::class, 'download'])->name('drafts.download');
});

// The magic-link entry point lives at the root so the emailed URL stays short.
Route::get('access/{token}', [MagicLinkController::class, 'consume'])
    ->middleware(['client.enabled', 'throttle:magic-link'])
    ->name('magic-link.consume');
