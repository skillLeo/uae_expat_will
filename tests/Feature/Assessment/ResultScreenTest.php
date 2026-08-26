<?php

/**
 * The two payment result screens, against the approved developer handoff of
 * August 2026. The copy is Summit's; these tests exist so it cannot drift.
 */

use App\Domain\Settings\Services\SettingsRepository;
use App\Models\Assessment;

beforeEach(function () {
    seedPlatform();
    seedQuestionnaire();
});

function completeTo(array $overrides = [])
{
    test()->get('/assessment');

    foreach (cleanAnswers($overrides) as $key => $value) {
        test()->post('/assessment/answer', ['question_key' => $key, 'value' => $value]);

        if ($key === 'q2') {
            test()->post('/assessment/contact', [
                'contact_name' => 'Aisha Rahman',
                'contact_email' => 'aisha@example.com',
                'contact_phone' => '+971 50 123 4567',
            ]);
        }
    }

    $declarations = Assessment::first()->version->declarations->pluck('id')->all();

    return test()->post('/assessment/submit', ['declarations' => $declarations]);
}

it('shows the single Will screen at the approved price', function () {
    completeTo();

    $this->get('/assessment/result')->assertInertia(fn ($p) => $p
        ->where('screen.heading', 'Continue with Our Online UAE Will Service')
        ->where('screen.primary_action_label', 'Continue and Pay AED 2,098.95')
        ->where('screen.secondary_action_label', 'I Have a Question Before Paying')
        ->where('fee.amount', 1999)
        ->where('fee.is_mirror', false)
        ->has('screen.extra.includes', 6)
        ->where('allowsPayment', true));
});

it('shows the mirror screen and its own price for two Wills', function () {
    completeTo(['q1' => 'two_wills']);

    $this->get('/assessment/result')->assertInertia(fn ($p) => $p
        ->where('screen.heading', 'Continue with Our Online Mirror Wills Service')
        ->where('screen.primary_action_label', 'Continue and Pay AED 3,148.95')
        ->where('fee.amount', 2999)
        ->where('fee.is_mirror', true)
        ->has('screen.extra.includes', 6)
        ->where('allowsPayment', true));
});

it('states on both screens that paying is not registering', function (array $answers, string $expected) {
    completeTo($answers);

    $this->get('/assessment/result')->assertInertia(fn ($p) => $p
        ->where('screen.extra.notice_heading', fn ($h) => str_contains($h, $expected)));
})->with([
    'single' => [[], 'Payment does not mean that your Will has been prepared'],
    'mirror' => [['q1' => 'two_wills'], 'Payment does not mean that either Will has been prepared'],
]);

it('never leaves the mirror block visible on a single Will result', function () {
    // The override is merged, not passed through, so a single-Will customer can
    // never be shown two-Will wording by a template that reads the wrong key.
    completeTo();

    $this->get('/assessment/result')
        ->assertInertia(fn ($p) => $p->missing('screen.extra.mirror'));
});

it('reads the price from settings rather than the seeded copy', function () {
    app(SettingsRepository::class)->set('commercial.standard_fee', 2500);

    completeTo();

    $this->get('/assessment/result')->assertInertia(fn ($p) => $p
        ->where('screen.primary_action_label', 'Continue and Pay AED 2,625.00')
        ->where('fee.amount', 2500));
});

it('offers no payment on a DIFC request', function () {
    // Since Summit's handoff of 26 August a DIFC client answers every question
    // and receives a review ticket, rather than being told the service is not
    // available. DifcReviewTicketTest covers the ticket itself.
    completeTo(['q1' => 'difc']);

    $this->get('/assessment/result')->assertInertia(fn ($p) => $p
        ->where('allowsPayment', false)
        ->where('screen.heading', 'Your DIFC Will Assessment Is Ready for Legal Review'));
});
