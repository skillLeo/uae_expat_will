<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\TwoFactorController;
use App\Http\Controllers\Admin\CaseController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin
|--------------------------------------------------------------------------
| Every route below carries a permission. The Vue layer hides controls a user
| cannot use, but that is cosmetic — this is where access is actually decided.
*/

Route::prefix('admin')->name('admin.')->group(function () {

    // ---------------------------------------------------------- guest routes
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login/identify', [LoginController::class, 'identify'])
            ->middleware('throttle:login')->name('login.identify');
        Route::post('login', [LoginController::class, 'store'])
            ->middleware('throttle:login')->name('login.store');
    });

    Route::get('disabled', [LoginController::class, 'disabled'])->name('disabled');
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // -------------------------------------------------- authenticated routes
    Route::middleware('auth:admin')->group(function () {

        // 2FA enrolment and challenge live OUTSIDE the 2FA gate, or an
        // unenrolled administrator could never enrol.
        Route::middleware('throttle:two-factor')->group(function () {
            Route::get('two-factor/enrol', [TwoFactorController::class, 'enrol'])->name('two-factor.enrol');
            Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
            Route::get('two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
            Route::get('two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
            Route::post('two-factor/verify', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
        });

        // Everything past this point requires enrolment AND a passed challenge.
        Route::middleware(['admin.2fa', 'admin.2fa.passed'])->group(function () {

            Route::get('/', DashboardController::class)->name('dashboard');

            Route::get('cases', [CaseController::class, 'index'])
                ->middleware('permission:cases.view.all|cases.view.assigned')
                ->name('cases.index');

            Route::get('cases/{case}', [CaseController::class, 'show'])
                ->middleware('permission:cases.view.all|cases.view.assigned')
                ->name('cases.show');
        });
    });
});
