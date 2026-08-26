<?php

/**
 * "Selecting DIFC must continue into the complete qualifying questionnaire.
 * When the final question is submitted, create a DIFC legal-review ticket and
 * do not show a payment screen." — Summit, 26 August 2026.
 */

use App\Domain\Cases\Enums\InternalStatus;
use App\Domain\Cases\Enums\RequestType;
use App\Models\Assessment;
use App\Models\LegalCase;

beforeEach(function () {
    seedPlatform();
    seedQuestionnaire();
});

function completeAs(string $service)
{
    test()->get('/assessment');

    foreach (cleanAnswers(['q1' => $service]) as $key => $value) {
        test()->post('/assessment/answer', ['question_key' => $key, 'value' => $value]);

        if ($key === 'q2') {
            test()->post('/assessment/contact', [
                'contact_name' => 'Aisha Rahman',
                'contact_email' => 'aisha@example.com',
                'contact_phone' => '+971500000000',
            ]);
        }
    }

    return test()->post('/assessment/submit', [
        'declarations' => Assessment::first()->version->declarations->pluck('id')->all(),
    ]);
}

it('creates a DIFC review ticket only after the last question', function () {
    completeAs('difc');

    $case = LegalCase::first();

    expect($case->request_type)->toBe(RequestType::DifcWill)
        ->and($case->internal_status)->toBe(InternalStatus::DifcLegalReviewRequired)
        ->and($case->assessment_id)->not->toBeNull()
        ->and($case->assigned_to)->toBeNull();
});

it('never prices or charges a DIFC matter', function () {
    completeAs('difc');

    $case = LegalCase::first();

    expect($case->quoted_amount)->toBeNull()
        ->and($case->allowsPayment())->toBeFalse();
});

it('shows the DIFC review screen instead of a checkout', function () {
    completeAs('difc');

    $this->get('/assessment/result')->assertInertia(fn ($p) => $p
        ->where('allowsPayment', false)
        ->where('screen.heading', 'Your DIFC Will Assessment Is Ready for Legal Review')
        ->where('screen.primary_action_label', 'View My Review Ticket')
        ->where('screen.secondary_action_label', 'Return to Homepage')
        ->where('screen.extra.callout_heading', 'No payment is required at this stage.')
        ->where('reference', LegalCase::first()->reference));
});

it('never shows a DIFC client the service-unavailable screen', function () {
    completeAs('difc');

    $this->get('/assessment/result')->assertInertia(fn ($p) => $p
        ->where('screen.heading', fn ($h) => ! str_contains(strtolower($h), 'not available')));
});

it('attaches the complete answers to the ticket', function () {
    completeAs('difc');

    $case = LegalCase::first();

    expect($case->assessment->answers()->count())->toBeGreaterThan(10)
        ->and($case->customer->email)->toBe('aisha@example.com');
});

it('still prices a standard Will and a mirror pair', function () {
    completeAs('new_will');
    expect((float) LegalCase::first()->quoted_amount)->toBe(1999.0);

    LegalCase::query()->forceDelete();
    Assessment::query()->forceDelete();

    completeAs('two_wills');
    expect((float) LegalCase::first()->quoted_amount)->toBe(2999.0);
});
