<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * The console is the only way back in for a locked-out administrator: there is
 * no forgot-password route on the admin side, and no mail server to deliver
 * one. So this command has to be right, and it has to be safe to hand to
 * whoever happens to have server access that day.
 */
beforeEach(function () {
    $this->user = User::factory()->create([
        'user_type' => User::TYPE_ADMIN,
        'email' => 'locked.out@example.com',
        'password' => 'the-old-password',
    ]);
});

it('sets a password that the account can then authenticate with', function () {
    $this->artisan('admin:password', ['email' => 'locked.out@example.com'])
        ->expectsConfirmation('Set a new password for this account?', 'yes')
        ->expectsQuestion('New password', 'a-properly-long-one')
        ->expectsQuestion('Type it again', 'a-properly-long-one')
        ->assertSuccessful();

    $this->user->refresh();

    expect(Hash::check('a-properly-long-one', $this->user->password))->toBeTrue()
        ->and(Hash::check('the-old-password', $this->user->password))->toBeFalse();
});

it('never prints the password back', function () {
    $this->artisan('admin:password', ['email' => 'locked.out@example.com'])
        ->expectsConfirmation('Set a new password for this account?', 'yes')
        ->expectsQuestion('New password', 'a-properly-long-one')
        ->expectsQuestion('Type it again', 'a-properly-long-one')
        ->doesntExpectOutputToContain('a-properly-long-one')
        ->assertSuccessful();
});

it('refuses an email that does not exist', function () {
    $this->artisan('admin:password', ['email' => 'nobody@example.com'])
        ->assertFailed();
});

it('changes nothing when the two entries disagree', function () {
    $this->artisan('admin:password', ['email' => 'locked.out@example.com'])
        ->expectsConfirmation('Set a new password for this account?', 'yes')
        ->expectsQuestion('New password', 'a-properly-long-one')
        ->expectsQuestion('Type it again', 'a-properly-long-typo')
        ->assertFailed();

    $this->user->refresh();

    expect(Hash::check('the-old-password', $this->user->password))->toBeTrue();
});

it('changes nothing when the operator backs out at the confirmation', function () {
    $this->artisan('admin:password', ['email' => 'locked.out@example.com'])
        ->expectsConfirmation('Set a new password for this account?', 'no')
        ->assertSuccessful();

    $this->user->refresh();

    expect(Hash::check('the-old-password', $this->user->password))->toBeTrue();
});

it('will not accept a password shorter than the platform requires', function () {
    $this->artisan('admin:password', ['email' => 'locked.out@example.com'])
        ->expectsConfirmation('Set a new password for this account?', 'yes')
        ->expectsQuestion('New password', 'too-short')
        ->expectsQuestion('Type it again', 'too-short')
        ->assertFailed();

    $this->user->refresh();

    expect(Hash::check('the-old-password', $this->user->password))->toBeTrue();
});

it('leaves two-factor alone unless asked', function () {
    $this->user->forceFill([
        'two_factor_secret' => 'a-secret',
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->artisan('admin:password', ['email' => 'locked.out@example.com'])
        ->expectsConfirmation('Set a new password for this account?', 'yes')
        ->expectsQuestion('New password', 'a-properly-long-one')
        ->expectsQuestion('Type it again', 'a-properly-long-one')
        ->assertSuccessful();

    expect($this->user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

it('clears two-factor when asked and confirmed, so they enrol again', function () {
    $this->user->forceFill([
        'two_factor_secret' => 'a-secret',
        'two_factor_recovery_codes' => ['one', 'two'],
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->artisan('admin:password', ['email' => 'locked.out@example.com', '--reset-2fa' => true])
        ->expectsConfirmation('Set a new password for this account?', 'yes')
        ->expectsQuestion('New password', 'a-properly-long-one')
        ->expectsQuestion('Type it again', 'a-properly-long-one')
        ->expectsConfirmation('Also clear two-factor, so they enrol again on next login?', 'yes')
        ->assertSuccessful();

    expect($this->user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

it('keeps two-factor when the flag is passed but the operator says no', function () {
    $this->user->forceFill([
        'two_factor_secret' => 'a-secret',
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->artisan('admin:password', ['email' => 'locked.out@example.com', '--reset-2fa' => true])
        ->expectsConfirmation('Set a new password for this account?', 'yes')
        ->expectsQuestion('New password', 'a-properly-long-one')
        ->expectsQuestion('Type it again', 'a-properly-long-one')
        ->expectsConfirmation('Also clear two-factor, so they enrol again on next login?', 'no')
        ->assertSuccessful();

    expect($this->user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});
