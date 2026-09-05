<?php

use App\Domain\Settings\RowSchemas;
use App\Models\Setting;

/**
 * Summit changing its own prices, without asking anyone.
 *
 * Every price on the site is a setting, and always has been — but the
 * authority-charge table was a JSON textarea, so changing it meant hand-typing
 * JSON and a single missing comma refused the whole save. Summit could not
 * change their own prices in practice, so they asked us to, and the request
 * travelled through screenshots and WhatsApp. Four days were then spent arguing
 * about a number that was never wrong.
 *
 * These tests hold the thing that ends that: a price is edited in the admin,
 * and the public page shows it.
 */
beforeEach(function () {
    seedPlatform();
    seedContent();
});

/**
 * Signed in with the second factor already passed for this session. Having a
 * secret on the account is not the same as having proved possession of the
 * device, and the admin area enforces that separately.
 */
function pricingAdmin(array $roles = ['Super Administrator']): void
{
    $user = adminUser($roles);
    test()->actingAs($user, 'admin')->withSession(['2fa.passed' => true]);
}

it('offers labelled fields for the authority table, not raw JSON', function () {
    pricingAdmin();

    $this->get('/admin/settings/commercial')
        ->assertOk()
        ->assertInertia(function ($page) {
            $settings = collect($page->toArray()['props']['settings']);
            $table = $settings->firstWhere('key', 'commercial.authority_fees');

            expect($table['row_schema'])->not->toBeNull('The authority table must not be a raw JSON box.');

            $columns = collect($table['row_schema']['columns'])->pluck('key');
            expect($columns)->toContain('route')->toContain('amount')->toContain('note');

            // "Add a route" needs every column present, or it renders a blank row.
            expect(array_keys($table['blank_row']))->toEqualCanonicalizing(['route', 'amount', 'note']);
        });
});

it('lets a Super Administrator change the professional fee, and the site follows', function () {
    pricingAdmin();

    $this->patchJson('/admin/settings/commercial', [
        'settings' => ['commercial.standard_fee' => 12345],
    ])->assertRedirect()->assertSessionHas('success');

    expect((int) setting('commercial.standard_fee'))->toBe(12345);

    // The whole point: it reaches the public page without a deploy.
    $this->get('/pricing')->assertOk()->assertSee('AED 12,345', false);
});

it('changes the mirror fee the Pricing page is given', function () {
    pricingAdmin();

    $this->patchJson('/admin/settings/commercial', [
        'settings' => ['commercial.mirror_fee' => 21000],
    ])->assertRedirect()->assertSessionHas('success');

    expect((int) setting('commercial.mirror_fee'))->toBe(21000);

    // The mirror figure is formatted in the browser, so a non-SSR test sees
    // the value rather than the rendered string. What matters is that the
    // page is handed the new number without a deploy.
    // The prop key contains dots, so the fluent path cannot address it —
    // read the shared settings bag directly.
    $this->get('/pricing')->assertOk()->assertInertia(function ($page) {
        expect($page->toArray()['props']['settings']['commercial.mirror_fee'])->toBe(21000);
    });
});

it('saves the authority table as rows, with no JSON to get wrong', function () {
    pricingAdmin();

    $rows = [
        ['route' => 'ADJD Civil Will', 'amount' => 'AED 1,100.00', 'note' => 'Updated by Summit'],
        ['route' => 'DIFC Courts Will', 'amount' => 'Varies by Will type', 'note' => 'Quoted individually'],
    ];

    $this->patchJson('/admin/settings/commercial', [
        'settings' => ['commercial.authority_fees' => $rows],
    ])->assertRedirect()->assertSessionHas('success');

    expect(setting('commercial.authority_fees'))->toBe($rows);

    $this->get('/pricing')->assertOk()
        ->assertSee('AED 1,100.00', false)
        ->assertSee('Varies by Will type', false);
});

it('accepts words in the charge column, not only numbers', function () {
    // The DIFC row genuinely has no single figure, and Dubai's carries a "≈".
    // A numeric field would make both impossible to state honestly.
    pricingAdmin();

    $this->patchJson('/admin/settings/commercial', [
        'settings' => ['commercial.authority_fees' => [
            ['route' => 'DIFC Courts Will', 'amount' => 'Depends on the Will type chosen', 'note' => ''],
        ]],
    ])->assertRedirect();

    $this->get('/pricing')->assertOk()->assertSee('Depends on the Will type chosen', false);
});

it('lets Summit remove a route entirely', function () {
    pricingAdmin();

    $this->patchJson('/admin/settings/commercial', [
        'settings' => ['commercial.authority_fees' => [
            ['route' => 'ADJD Civil Will', 'amount' => 'AED 950.00', 'note' => 'Only this one'],
        ]],
    ])->assertRedirect();

    $page = $this->get('/pricing')->assertOk();
    $page->assertSee('ADJD Civil Will', false);
    $page->assertDontSee('Dubai Courts Will', false);
});

it('tells the administrator where each price appears', function () {
    // The confusion was never a wrong number. It was that three unrelated
    // kinds of price sit on one page and nothing said which was which.
    $help = Setting::whereIn('key', [
        'commercial.standard_fee',
        'commercial.difc_starting_fee',
        'commercial.authority_fees',
    ])->pluck('help_text', 'key');

    expect($help['commercial.standard_fee'])->toContain('Pricing page');
    expect($help['commercial.difc_starting_fee'])->toContain('DIFC card');

    // The line that settles the argument outright.
    expect($help['commercial.authority_fees'])
        ->toContain('not Summit')
        ->toContain('never receives');
});

it('refuses a read-only user trying to change a price', function () {
    pricingAdmin(['Read Only']);

    $this->patchJson('/admin/settings/commercial', [
        'settings' => ['commercial.standard_fee' => 1],
    ])->assertForbidden();

    expect((int) setting('commercial.standard_fee'))->not->toBe(1);
});

it('records who changed a price', function () {
    // Summit changing their own prices only works if there is a trail showing
    // what changed and who did it.
    pricingAdmin();

    $this->patchJson('/admin/settings/commercial', [
        'settings' => ['commercial.standard_fee' => 11111],
    ])->assertRedirect();

    $this->get('/admin/settings/commercial')
        ->assertInertia(function ($page) {
            $history = collect($page->toArray()['props']['history']);

            expect($history->where('key', 'commercial.standard_fee'))->not->toBeEmpty();
        });
});

it('keeps a schema and a blank row in step with each other', function () {
    $schema = RowSchemas::for('commercial.authority_fees');
    $blank = RowSchemas::blankRow('commercial.authority_fees');

    expect(array_keys($blank))->toEqualCanonicalizing(array_column($schema['columns'], 'key'));
    expect(RowSchemas::for('commercial.standard_fee'))->toBeNull();
});
