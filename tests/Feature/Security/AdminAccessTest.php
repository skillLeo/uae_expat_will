<?php

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\InternalStatus;
use App\Domain\Settings\Services\SettingsRepository;
use App\Models\LegalCase;
use App\Models\User;

beforeEach(function () {
    seedPlatform();

    $this->case = LegalCase::create([
        'reference' => 'SLC-2026-00030',
        'status' => CaseStatus::UnderReview,
        'internal_status' => InternalStatus::HeldSensitiveMatter,
    ]);
});

/** Signs in and marks the 2FA challenge passed for this session. */
function signIn(array $roles = ['Super Administrator']): User
{
    $user = adminUser($roles);
    test()->actingAs($user, 'admin')->withSession(['2fa.passed' => true]);

    return $user;
}

// --------------------------------------------------------------- guest gate

it('redirects a guest away from every admin route', function (string $route) {
    $this->get($route)->assertRedirect();
})->with([
    '/admin',
    '/admin/cases',
]);

it('lets a guest reach the login screen', function () {
    $this->get('/admin/login')->assertOk();
});

// ------------------------------------------------------------ the 2FA gate

it('forces an unenrolled administrator to enrol before anything else', function () {
    $user = User::factory()->create(['user_type' => User::TYPE_ADMIN]);
    $user->syncRoles(['Super Administrator']);

    $this->actingAs($user->fresh(), 'admin')
        ->get('/admin')
        ->assertRedirect(route('admin.two-factor.enrol'));
});

it('forces an enrolled administrator to pass the challenge each session', function () {
    // Holding a secret is not the same as having proved possession of the
    // device in THIS session.
    $this->actingAs(adminUser(), 'admin')
        ->get('/admin')
        ->assertRedirect(route('admin.two-factor.challenge'));
});

it('lets an administrator through once the challenge is passed', function () {
    signIn();

    $this->get('/admin')->assertOk();
});

// ------------------------------------------------------- permission checks

it('allows a role that holds the permission', function (string $role) {
    signIn([$role]);

    $this->get('/admin/cases')->assertOk();
})->with(['Super Administrator', 'Administrator', 'Legal Reviewer', 'Case Handler', 'Finance', 'Read Only']);

it('blocks a role that holds no case permission at all', function () {
    $user = adminUser([]);
    $this->actingAs($user, 'admin')->withSession(['2fa.passed' => true]);

    $this->get('/admin/cases')->assertForbidden();
});

it('enforces the permission on the server even when the client asks directly', function () {
    $user = adminUser([]);
    $this->actingAs($user, 'admin')->withSession(['2fa.passed' => true]);

    // The Vue layer hides the control; this is where access is actually decided.
    $this->get('/admin/cases/'.$this->case->id)->assertForbidden();
});

it('ships only the permissions a user actually holds', function () {
    signIn(['Read Only']);

    $this->get('/admin')->assertInertia(
        fn ($page) => $page
            ->where('auth.permissions', fn ($perms) => collect($perms)->contains('cases.view.all')
                && ! collect($perms)->contains('payments.refund')
                && ! collect($perms)->contains('cases.view_restricted'))
    );
});

it('never ships a secret setting to the browser', function () {
    app(SettingsRepository::class)->set('payment.auth_key', 'top-secret');
    signIn();

    $response = $this->get('/admin');

    $response->assertOk();
    expect($response->getContent())->not->toContain('top-secret');
});

// -------------------------------------------------------- disabled account

it('sends a disabled account to the disabled screen instead of signing it in', function () {
    $admin = adminUser();
    $disabler = adminUser();

    $admin->forceFill([
        'password' => bcrypt('correct-horse'),
        'is_active' => false,
        'disabled_at' => now(),
        'disabled_by' => $disabler->id,
        'disabled_reason' => 'Left the firm',
    ])->save();

    $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'correct-horse',
    ])->assertRedirect(route('admin.disabled'));

    expect(auth('admin')->check())->toBeFalse();
});

it('states who disabled the account and why', function () {
    $admin = adminUser();
    $disabler = adminUser();
    $admin->forceFill([
        'is_active' => false, 'disabled_at' => now(),
        'disabled_by' => $disabler->id, 'disabled_reason' => 'Left the firm',
    ])->save();

    $this->withSession(['disabled_user_id' => $admin->id])
        ->get('/admin/disabled')
        ->assertInertia(fn ($page) => $page
            ->where('reason', 'Left the firm')
            ->where('disabledBy', $disabler->name));
});

// ------------------------------------------------------- account enumeration

it('never reveals whether an email exists', function () {
    $admin = adminUser();
    $admin->forceFill(['password' => bcrypt('correct-horse')])->save();

    $known = $this->from('/admin/login')->post('/admin/login', [
        'email' => $admin->email, 'password' => 'wrong-password',
    ]);

    $unknown = $this->from('/admin/login')->post('/admin/login', [
        'email' => 'nobody@example.com', 'password' => 'wrong-password',
    ]);

    // Identical response either way, or the form becomes a staff directory.
    $knownMessage = $known->assertRedirect('/admin/login')
        ->assertSessionHasErrors('password')
        ->getSession()->get('errors')->first('password');

    $unknownMessage = $unknown->assertRedirect('/admin/login')
        ->assertSessionHasErrors('password')
        ->getSession()->get('errors')->first('password');

    expect($knownMessage)
        ->toBe('Those details do not match our records.')
        ->toBe($unknownMessage);
});

it('locks an account after too many failed attempts', function () {
    $admin = adminUser();
    $admin->forceFill(['password' => bcrypt('correct-horse')])->save();
    $max = (int) setting('security.max_login_attempts', 5);

    for ($i = 0; $i < $max; $i++) {
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong']);
    }

    expect($admin->fresh()->isLockedOut())->toBeTrue()
        ->and($admin->fresh()->secondsUntilUnlock())->toBeGreaterThan(0);
});

it('logs a failed sign-in without storing the email in clear', function () {
    $this->post('/admin/login', ['email' => 'someone@example.com', 'password' => 'wrong']);

    $entry = DB::table('activity_log')->where('log_name', 'auth')->latest('id')->first();

    expect($entry->description)->toBe('Failed sign-in attempt')
        ->and($entry->properties)->not->toContain('someone@example.com');
});
