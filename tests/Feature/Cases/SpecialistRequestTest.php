<?php

/**
 * Amending an existing Will and administering an estate after a death used to
 * be answered with a page saying the online Will service is not available.
 * They are different legal services, not rejections, and Ahmed's instruction
 * of 25 August was that both should skip the questionnaire and go straight to
 * the team: "number 4 and 5 no questions at all, goes to contact team".
 *
 * Nothing here concerns DIFC, which belongs to a different project.
 */

use App\Domain\Cases\Enums\InternalStatus;
use App\Domain\Cases\Enums\RequestType;
use App\Models\Consent;
use App\Models\LegalCase;

beforeEach(function () {
    seedPlatform();
    seedQuestionnaire();
    $this->get('/assessment');
});

function chooseService(string $answer)
{
    return test()->post('/assessment/answer', ['question_key' => 'q1', 'value' => $answer]);
}

// ------------------------------------------------------------------ routing

it('sends an existing-Will enquiry to the request form, not a rejection', function () {
    chooseService('review_existing');

    $this->get('/assessment')->assertRedirect('/specialist-request/existing_will_service');
});

it('sends an estate enquiry to the request form, not a rejection', function () {
    chooseService('estate_death');

    $this->get('/assessment')->assertRedirect('/specialist-request/estate_administration');
});

it('sends only those two to the form, and leaves every other option alone', function () {
    // A DIFC request is not a specialist request on this project. It behaves
    // as it always has: through the questionnaire, ending on the review
    // screen with no payment.
    chooseService('difc');

    $this->get('/assessment')->assertInertia(fn ($p) => $p
        ->component('Assessment/Question')
        ->where('question.key', 'q2'));
});

it('still shows the rejection screen for a genuine hard stop', function () {
    chooseService('new_will');
    $this->post('/assessment/answer', ['question_key' => 'q2', 'value' => 'no']);

    $this->get('/assessment')->assertInertia(fn ($p) => $p
        ->component('Assessment/Terminal')
        ->where('outcome', 'stop_ineligible'));
});

// -------------------------------------------------------------- the form

it('shows the right service note for each request type', function (string $service, string $expected) {
    $this->get("/specialist-request/{$service}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->where('step', 1)
            ->where('serviceNote', fn ($n) => str_contains($n, $expected)));
})->with([
    'existing will' => ['existing_will_service', 'review, amend, replace or revoke'],
    'estate' => ['estate_administration', 'estate administration or succession matter'],
]);

it('refuses a service that is not a specialist request', function () {
    $this->get('/specialist-request/standard_will')->assertRedirect('/assessment');
});

it('saves the lead on step one, before anything else is asked', function () {
    // The whole point: somebody who closes the tab now is still contactable.
    $this->post('/specialist-request/existing_will_service/contact', [
        'full_name' => 'Aisha Rahman',
        'email' => 'aisha@example.com',
        'phone' => '+971 50 123 4567',
        'country_of_residence' => 'GB',
        'preferred_contact_method' => 'whatsapp',
    ])->assertSessionHasNoErrors();

    $case = LegalCase::first();

    expect($case)->not->toBeNull()
        ->and($case->request_type)->toBe(RequestType::ExistingWillService)
        ->and($case->internal_status)->toBe(InternalStatus::ContactCapturedIncomplete)
        ->and($case->reference)->toStartWith('SLC-')
        ->and($case->customer->preferred_contact_method)->toBe('whatsapp')
        ->and($case->assigned_to)->toBeNull();
});

it('never prices a specialist request', function () {
    $this->post('/specialist-request/estate_administration/contact', [
        'full_name' => 'Aisha Rahman', 'email' => 'aisha@example.com',
        'phone' => '+971500000000', 'country_of_residence' => 'GB',
        'preferred_contact_method' => 'email',
    ]);

    $case = LegalCase::first();

    expect($case->quoted_amount)->toBeNull()
        ->and($case->allowsPayment())->toBeFalse();
});

it('validates every field the handoff marks required', function () {
    $this->post('/specialist-request/existing_will_service/contact', [])
        ->assertSessionHasErrors([
            'full_name', 'email', 'phone', 'country_of_residence', 'preferred_contact_method',
        ]);
});

it('updates the same case on step two instead of making a second one', function () {
    $this->post('/specialist-request/existing_will_service/contact', [
        'full_name' => 'Aisha Rahman', 'email' => 'aisha@example.com',
        'phone' => '+971500000000', 'country_of_residence' => 'GB',
        'preferred_contact_method' => 'email',
    ]);

    $this->post('/specialist-request/existing_will_service', [
        'brief_description' => 'I have a will from 2019 in the UK and want to know if it covers my Dubai flat.',
        'consent' => true,
    ])->assertRedirect('/request-received');

    expect(LegalCase::count())->toBe(1);

    $case = LegalCase::first();

    expect($case->internal_status)->toBe(InternalStatus::NewLegalReviewRequired)
        ->and($case->brief_description)->toContain('Dubai flat')
        ->and(Consent::where('case_id', $case->id)->where('type', 'specialist_request')->exists())->toBeTrue();
});

it('will not submit details without consent', function () {
    $this->post('/specialist-request/existing_will_service/contact', [
        'full_name' => 'Aisha Rahman', 'email' => 'aisha@example.com',
        'phone' => '+971500000000', 'country_of_residence' => 'GB',
        'preferred_contact_method' => 'email',
    ]);

    $this->post('/specialist-request/existing_will_service', [
        'brief_description' => 'A perfectly reasonable description of my matter.',
        'consent' => false,
    ])->assertSessionHasErrors('consent');

    expect(LegalCase::first()->internal_status)->toBe(InternalStatus::ContactCapturedIncomplete);
});

it('shows the confirmation with the reference', function () {
    $this->post('/specialist-request/estate_administration/contact', [
        'full_name' => 'Aisha Rahman', 'email' => 'aisha@example.com',
        'phone' => '+971500000000', 'country_of_residence' => 'GB',
        'preferred_contact_method' => 'telephone',
    ]);
    $this->post('/specialist-request/estate_administration', [
        'brief_description' => 'My father passed away and owned a property in Dubai.',
        'consent' => true,
    ]);

    $this->get('/request-received')->assertOk()->assertInertia(fn ($p) => $p
        ->where('reference', LegalCase::first()->reference)
        ->where('copy.heading', 'Your Request Has Been Received')
        ->where('copy.payment', 'No payment has been taken.'));
});
