<?php

use App\Models\Assessment;

/**
 * Partner details on a mirror-Wills assessment.
 *
 * Agreed with Summit on 28 August 2026. The pair is the service: the first
 * person pays for both, so their partner has to be reachable before payment,
 * not chased for afterwards.
 *
 * The nationality list is the one the nationality question uses, with the UAE
 * removed. That is what answers the question Summit left open — "what happens
 * if the partner turns out to be a UAE national" — there is no branch to
 * write, because an ineligible partner cannot be entered in the first place.
 */
beforeEach(function () {
    seedPlatform();
    seedQuestionnaire();
});

/** An assessment sitting on the contact step, for the service given. */
function atContactStep(string $service): Assessment
{
    // The first GET is what creates the assessment and puts its token in the
    // session; answering before that has nothing to answer against.
    test()->get('/assessment');
    test()->post('/assessment/answer', ['question_key' => 'q1', 'value' => $service]);
    test()->post('/assessment/answer', ['question_key' => 'q2', 'value' => 'yes']);

    return Assessment::latest('id')->firstOrFail();
}

it('asks for the partner on a mirror assessment', function () {
    atContactStep('two_wills');

    $this->get('/assessment')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Contact')
            ->where('isMirror', true)
            ->has('countries')
        );
});

it('does not ask for a partner on a single Will', function () {
    atContactStep('new_will');

    $this->get('/assessment')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Assessment/Contact')
            ->where('isMirror', false)
            ->where('countries', null)
        );
});

it('never offers the UAE as a partner nationality', function () {
    atContactStep('two_wills');

    $this->get('/assessment')->assertInertia(function ($page) {
        $codes = collect($page->toArray()['props']['countries'])->pluck('code');

        expect($codes)->not->toContain('AE')
            ->and($codes->count())->toBeGreaterThan(100);
    });
});

it('carries the "not available to UAE citizens" line onto the partner field', function () {
    atContactStep('two_wills');

    $this->get('/assessment')->assertInertia(fn ($page) => $page
        ->where('partnerNotice', 'The Will services available through this platform are not available to UAE citizens.')
    );
});

it('will not continue without the partner details', function () {
    atContactStep('two_wills');

    $this->post('/assessment/contact', [
        'contact_name' => 'First Person',
        'contact_email' => 'first@example.com',
        'contact_phone' => '+971500000000',
    ])->assertSessionHasErrors(['partner_name', 'partner_nationality', 'partner_phone', 'partner_email']);
});

it('saves the partner when every detail is given', function () {
    $assessment = atContactStep('two_wills');

    $this->post('/assessment/contact', [
        'contact_name' => 'First Person',
        'contact_email' => 'first@example.com',
        'contact_phone' => '+971500000000',
        'partner_name' => 'Second Person',
        'partner_nationality' => 'GB',
        'partner_phone' => '+971500000001',
        'partner_email' => 'second@example.com',
        'partner_email_confirmation' => 'second@example.com',
    ])->assertSessionHasNoErrors();

    expect($assessment->fresh()->partner())->toMatchArray([
        'name' => 'Second Person',
        'nationality' => 'GB',
        'phone' => '+971500000001',
        'email' => 'second@example.com',
    ]);
});

it('rejects a partner email that was mistyped the second time', function () {
    atContactStep('two_wills');

    $this->post('/assessment/contact', [
        'contact_name' => 'First Person',
        'contact_email' => 'first@example.com',
        'contact_phone' => '+971500000000',
        'partner_name' => 'Second Person',
        'partner_nationality' => 'GB',
        'partner_phone' => '+971500000001',
        'partner_email' => 'second@example.com',
        'partner_email_confirmation' => 'secnod@example.com',
    ])->assertSessionHasErrors('partner_email');
});

it('refuses the applicant\'s own address as the partner address', function () {
    // Both Wills would otherwise be sent to one inbox, and the second person
    // would never independently approve their own Will.
    atContactStep('two_wills');

    $this->post('/assessment/contact', [
        'contact_name' => 'First Person',
        'contact_email' => 'same@example.com',
        'contact_phone' => '+971500000000',
        'partner_name' => 'Second Person',
        'partner_nationality' => 'GB',
        'partner_phone' => '+971500000001',
        'partner_email' => 'same@example.com',
        'partner_email_confirmation' => 'same@example.com',
    ])->assertSessionHasErrors('partner_email');
});

it('refuses a nationality that is not on the list', function () {
    atContactStep('two_wills');

    foreach (['AE', 'ZZ', 'not-a-country'] as $bad) {
        $this->post('/assessment/contact', [
            'contact_name' => 'First Person',
            'contact_email' => 'first@example.com',
            'contact_phone' => '+971500000000',
            'partner_name' => 'Second Person',
            'partner_nationality' => $bad,
            'partner_phone' => '+971500000001',
            'partner_email' => 'second@example.com',
            'partner_email_confirmation' => 'second@example.com',
        ])->assertSessionHasErrors('partner_nationality');
    }
});

it('still lets a single Will through with only the applicant', function () {
    $assessment = atContactStep('new_will');

    $this->post('/assessment/contact', [
        'contact_name' => 'Only Person',
        'contact_email' => 'only@example.com',
        'contact_phone' => '+971500000000',
    ])->assertSessionHasNoErrors();

    expect($assessment->fresh()->contact_captured_at)->not->toBeNull();
});
