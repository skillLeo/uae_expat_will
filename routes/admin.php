<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\TwoFactorController;
use App\Http\Controllers\Admin\CaseActionController;
use App\Http\Controllers\Admin\CaseController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\NotificationTemplateController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\QuestionnaireController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
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

        // 2FA enrolment and challenge sit OUTSIDE the 2FA gate, or an
        // unenrolled administrator could never enrol.
        Route::middleware('throttle:two-factor')->group(function () {
            Route::get('two-factor/enrol', [TwoFactorController::class, 'enrol'])->name('two-factor.enrol');
            Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
            Route::get('two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
            Route::get('two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
            Route::post('two-factor/verify', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
        });

        Route::middleware(['admin.2fa', 'admin.2fa.passed'])->group(function () {

            Route::get('/', DashboardController::class)->name('dashboard');

            // ------------------------------------------------------- cases
            Route::middleware('permission:cases.view.all|cases.view.assigned')->group(function () {
                Route::get('cases', [CaseController::class, 'index'])->name('cases.index');
                Route::get('cases/{case}', [CaseController::class, 'show'])->name('cases.show');
            });

            Route::middleware('permission:cases.update')->group(function () {
                Route::post('cases/{case}/status', [CaseActionController::class, 'changeStatus'])->name('cases.status');
                Route::post('cases/{case}/stage', [CaseActionController::class, 'recordStage'])->name('cases.stage');
                Route::post('cases/{case}/countdown', [CaseActionController::class, 'setCountdown'])->name('cases.countdown');
                Route::post('cases/{case}/magic-link', [CaseActionController::class, 'issueMagicLink'])->name('cases.magic-link');
            });

            Route::post('cases/{case}/assign', [CaseActionController::class, 'assign'])
                ->middleware('permission:cases.assign')->name('cases.assign');
            Route::post('cases/{case}/notes', [CaseActionController::class, 'addNote'])
                ->middleware('permission:notes.create')->name('cases.notes');
            Route::post('cases/{case}/contacts', [CaseActionController::class, 'logContact'])
                ->middleware('permission:contacts.log')->name('cases.contacts');

            // ---------------------------------------------------- payments
            Route::middleware('permission:payments.view')->group(function () {
                Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
                Route::get('payments/{payment}/refund-preview', [PaymentController::class, 'refundPreview'])->name('payments.refund-preview');
                Route::post('payments/{payment}/check', [PaymentController::class, 'checkStatus'])->name('payments.check');
            });

            Route::post('cases/{case}/payment-link', [PaymentController::class, 'createLink'])
                ->middleware('permission:payments.create_link')->name('payments.link');
            Route::post('cases/{case}/manual-payment', [PaymentController::class, 'recordManual'])
                ->middleware('permission:payments.record_manual')->name('payments.manual');
            Route::post('payments/{payment}/refund', [PaymentController::class, 'refund'])
                ->middleware('permission:payments.refund')->name('payments.refund');

            // ----------------------------------------------------- content
            Route::middleware('permission:content.view')->group(function () {
                Route::get('content', [ContentController::class, 'index'])->name('content.index');
                Route::get('content/faqs', [ContentController::class, 'faqs'])->name('content.faqs');
                Route::get('content/{page}', [ContentController::class, 'edit'])->name('content.edit');
            });

            Route::middleware('permission:content.edit')->group(function () {
                Route::patch('content/{page}', [ContentController::class, 'updatePage'])->name('content.update');
                Route::patch('content/sections/{section}', [ContentController::class, 'updateSection'])->name('content.section.update');
                Route::post('content/{page}/reorder', [ContentController::class, 'reorderSections'])->name('content.reorder');
                Route::post('content/faqs', [ContentController::class, 'storeFaq'])->name('content.faqs.store');
                Route::patch('content/faqs/{faq}', [ContentController::class, 'updateFaq'])->name('content.faqs.update');
                Route::delete('content/faqs/{faq}', [ContentController::class, 'destroyFaq'])->name('content.faqs.destroy');
                Route::post('content/faqs/reorder', [ContentController::class, 'reorderFaqs'])->name('content.faqs.reorder');
            });

            Route::post('content/{page}/publish', [ContentController::class, 'publishPage'])
                ->middleware('permission:content.publish')->name('content.publish');

            // ----------------------------------------------- questionnaire
            Route::middleware('permission:questionnaire.view')->group(function () {
                Route::get('questionnaire', [QuestionnaireController::class, 'index'])->name('questionnaire.index');
                Route::get('questionnaire/{version}', [QuestionnaireController::class, 'show'])->name('questionnaire.show');
                Route::post('questionnaire/{version}/preview', [QuestionnaireController::class, 'runPreview'])->name('questionnaire.preview');
            });

            Route::middleware('permission:questionnaire.edit')->group(function () {
                Route::post('questionnaire/draft', [QuestionnaireController::class, 'createDraft'])->name('questionnaire.draft');
                Route::post('questionnaire/{version}/questions', [QuestionnaireController::class, 'storeQuestion'])->name('questionnaire.questions.store');
                Route::patch('questionnaire/questions/{question}', [QuestionnaireController::class, 'updateQuestion'])->name('questionnaire.questions.update');
                Route::delete('questionnaire/questions/{question}', [QuestionnaireController::class, 'destroyQuestion'])->name('questionnaire.questions.destroy');
                Route::post('questionnaire/{version}/questions/reorder', [QuestionnaireController::class, 'reorderQuestions'])->name('questionnaire.questions.reorder');
                Route::post('questionnaire/questions/{question}/options', [QuestionnaireController::class, 'storeOption'])->name('questionnaire.options.store');
                Route::patch('questionnaire/options/{option}', [QuestionnaireController::class, 'updateOption'])->name('questionnaire.options.update');
                Route::delete('questionnaire/options/{option}', [QuestionnaireController::class, 'destroyOption'])->name('questionnaire.options.destroy');
                Route::post('questionnaire/questions/{question}/conditions', [QuestionnaireController::class, 'storeCondition'])->name('questionnaire.conditions.store');
                Route::delete('questionnaire/conditions/{condition}', [QuestionnaireController::class, 'destroyCondition'])->name('questionnaire.conditions.destroy');
                Route::post('questionnaire/{version}/rules', [QuestionnaireController::class, 'storeRule'])->name('questionnaire.rules.store');
                Route::patch('questionnaire/rules/{rule}', [QuestionnaireController::class, 'updateRule'])->name('questionnaire.rules.update');
                Route::delete('questionnaire/rules/{rule}', [QuestionnaireController::class, 'destroyRule'])->name('questionnaire.rules.destroy');
            });

            Route::post('questionnaire/{version}/publish', [QuestionnaireController::class, 'publish'])
                ->middleware('permission:questionnaire.publish')->name('questionnaire.publish');
            Route::post('questionnaire/{version}/rollback', [QuestionnaireController::class, 'rollback'])
                ->middleware('permission:questionnaire.rollback')->name('questionnaire.rollback');

            // ---------------------------------------------- notifications
            Route::middleware('permission:settings.view')->group(function () {
                Route::get('notifications', [NotificationTemplateController::class, 'index'])->name('notifications.index');
                Route::get('notifications/{template}', [NotificationTemplateController::class, 'edit'])->name('notifications.edit');
            });

            Route::middleware('permission:settings.edit')->group(function () {
                Route::patch('notifications/{template}', [NotificationTemplateController::class, 'update'])->name('notifications.update');
                Route::post('notifications/{template}/test', [NotificationTemplateController::class, 'test'])->name('notifications.test');
            });

            // ---------------------------------------------------- settings
            Route::get('settings/{group?}', [SettingsController::class, 'index'])
                ->middleware('permission:settings.view')->name('settings.index');

            Route::middleware('permission:settings.edit')->group(function () {
                Route::patch('settings/{group}', [SettingsController::class, 'update'])->name('settings.update');
                Route::post('settings/test/mail', [SettingsController::class, 'testMail'])->name('settings.test.mail');
                Route::post('settings/test/whatsapp', [SettingsController::class, 'testWhatsApp'])->name('settings.test.whatsapp');
                Route::post('settings/test/gateway', [SettingsController::class, 'testGateway'])->name('settings.test.gateway');
            });

            // ------------------------------------------------------- users
            Route::middleware('permission:users.view')->group(function () {
                Route::get('users', [UserController::class, 'index'])->name('users.index');
                Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
            });

            Route::post('users', [UserController::class, 'store'])
                ->middleware('permission:users.create')->name('users.store');

            Route::middleware('permission:users.update')->group(function () {
                Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
                Route::post('users/{user}/reset-2fa', [UserController::class, 'resetTwoFactor'])->name('users.reset-2fa');
            });

            Route::middleware('permission:users.disable')->group(function () {
                Route::post('users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
                Route::post('users/{user}/enable', [UserController::class, 'enable'])->name('users.enable');
                Route::post('users/{user}/revoke-sessions', [UserController::class, 'revokeSessions'])->name('users.revoke-sessions');
                Route::post('devices/{device}/revoke', [UserController::class, 'revokeDevice'])->name('users.revoke-device');
            });

            // ------------------------------------------------------- roles
            Route::middleware('permission:roles.view')->group(function () {
                Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
                Route::get('roles/{role}/preview', [RoleController::class, 'preview'])->name('roles.preview');
            });

            Route::post('roles', [RoleController::class, 'store'])
                ->middleware('permission:roles.create')->name('roles.store');
            Route::patch('roles/{role}', [RoleController::class, 'update'])
                ->middleware('permission:roles.update')->name('roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])
                ->middleware('permission:roles.update')->name('roles.destroy');

            // ------------------------------------------------------- audit
            Route::get('audit', [AuditController::class, 'index'])
                ->middleware('permission:audit.view')->name('audit.index');
            Route::get('audit/export', [AuditController::class, 'export'])
                ->middleware('permission:audit.export')->name('audit.export');
            Route::get('consents', [AuditController::class, 'consents'])
                ->middleware('permission:audit.view')->name('consents.index');
            Route::get('consents/export', [AuditController::class, 'exportConsents'])
                ->middleware('permission:consents.export')->name('consents.export');

            // --------------------------------------------------- analytics
            Route::get('analytics', AnalyticsController::class)
                ->middleware('permission:analytics.view')->name('analytics.index');
        });
    });
});
