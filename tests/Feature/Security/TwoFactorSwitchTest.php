<?php

use App\Domain\Settings\Services\SettingsRepository;
use App\Domain\System\Enums\HealthState;
use App\Domain\System\Services\SystemHealth;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Turning two-factor off, and back on.
 *
 * The per-role switches in Settings → Security existed from the beginning,
 * saved without complaint, and changed nothing: requiresTwoFactor() ended in a
 * bare `return true`, so every path returned true whatever the settings said.
 * A switch that appears to work and does not is worse than no switch, because
 * someone acts on the belief that they have turned it off.
 *
 * It works now, which makes these tests the thing standing between a
 * convenience during setup and a permanent hole. The last one is the important
 * one: while it is off, the dashboard says so in as many words.
 */
beforeEach(function () {
    seedPlatform();
    Cache::flush();
});

function enforce2fa(string $role, bool $on): void
{
    app(SettingsRepository::class)->set('security.enforce_2fa_'.$role, $on);
    Cache::flush();
}

it('sends an unenrolled administrator to enrolment while enforcement is on', function () {
    enforce2fa('super_administrator', true);

    // adminUser() enrols by default, so strip it: this is the state a brand
    // new administrator is in on their very first sign-in.
    $user = adminUser();
    $user->forceFill([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->actingAs($user->fresh(), 'admin')
        ->get('/admin')
        ->assertRedirect(route('admin.two-factor.enrol'));
});

it('lets an administrator straight in when enforcement is off', function () {
    enforce2fa('super_administrator', false);

    // No secret, no code, no enrolment — email and password only.
    $user = adminUser();
    $user->forceFill([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ])->save();

    expect($user->fresh()->requiresTwoFactor())->toBeFalse();

    $this->actingAs($user->fresh(), 'admin')
        ->get('/admin')
        ->assertOk();
});

it('does not challenge an already-enrolled administrator once enforcement is off', function () {
    // Otherwise whoever just switched it off is still asked for a code, and
    // concludes the switch is broken.
    enforce2fa('super_administrator', false);

    $user = adminUser(); // adminUser() enrols by default

    expect($user->hasTwoFactorEnabled())->toBeTrue();

    $this->actingAs($user, 'admin')
        ->get('/admin')
        ->assertOk();
});

it('challenges an enrolled administrator again the moment it is switched back on', function () {
    // The secret is left on the account, so switching back on restores the
    // code immediately with nothing to set up again.
    enforce2fa('super_administrator', true);

    $user = adminUser();

    $this->actingAs($user, 'admin')
        ->get('/admin')
        ->assertRedirect(route('admin.two-factor.challenge'));
});

it('keeps requiring it when any one of several roles requires it', function () {
    // Holding a second, laxer role must never be a way out of the stricter one.
    enforce2fa('super_administrator', true);
    enforce2fa('read_only', false);

    $user = adminUser(['Super Administrator', 'Read Only']);

    expect($user->requiresTwoFactor())->toBeTrue();
});

it('requires it for an account with no roles at all', function () {
    // An unknown state is not a reason to relax the requirement.
    $user = adminUser();
    $user->syncRoles([]);

    expect($user->fresh()->requiresTwoFactor())->toBeTrue();
});

it('never requires it of a customer', function () {
    $customer = User::factory()->create(['user_type' => User::TYPE_CUSTOMER]);

    expect($customer->requiresTwoFactor())->toBeFalse();
});

it('reports the dashboard as critical while two-factor is off', function () {
    enforce2fa('super_administrator', false);

    $check = collect(app(SystemHealth::class)->run())->firstWhere('key', 'two_factor');

    expect($check->state)->toBe(HealthState::Critical)
        ->and($check->summary)->toContain('Super Administrator')
        ->and($check->consequence)->toContain('password');
});

it('reports healthy once every role requires it again', function () {
    foreach (['super_administrator', 'administrator', 'legal_reviewer', 'case_handler', 'finance', 'read_only'] as $role) {
        enforce2fa($role, true);
    }

    $check = collect(app(SystemHealth::class)->run())->firstWhere('key', 'two_factor');

    expect($check->state)->toBe(HealthState::Healthy);
});

it('names every role that has it switched off', function () {
    enforce2fa('super_administrator', false);
    enforce2fa('finance', false);

    $check = collect(app(SystemHealth::class)->run())->firstWhere('key', 'two_factor');

    expect($check->summary)->toContain('Super Administrator')->toContain('Finance');
});

it('defaults to requiring it when the setting is missing entirely', function () {
    Setting::where('key', 'security.enforce_2fa_super_administrator')->delete();
    Cache::flush();

    expect(adminUser()->requiresTwoFactor())->toBeTrue();
});
