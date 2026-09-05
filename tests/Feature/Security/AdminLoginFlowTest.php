<?php

use App\Models\User;

/**
 * The two-step admin sign-in, from the browser's point of view.
 *
 * Step one takes the email and step two takes the password, so the form cannot
 * be used to find out which addresses belong to Summit staff. Inertia reuses
 * the same page component across both steps: it swaps the props and never runs
 * setup() again.
 *
 * That is what broke it. The email lived in the second form's own state,
 * seeded from props at setup — which on step one is null. So step two posted a
 * blank email, failed validation on a field the form did not display, and
 * looked to the person signing in like the button doing nothing at all. They
 * clicked repeatedly and it only worked after a full page reload.
 *
 * These tests hold the two halves of the fix: the email is taken from the
 * server on submit, and no failure is ever silent.
 */
beforeEach(function () {
    seedPlatform();

    $this->admin = User::factory()->create([
        'user_type' => User::TYPE_ADMIN,
        'email' => 'staff@summitlegaluae.com',
        'password' => 'CorrectHorse123!',
        'is_active' => true,
    ]);
    $this->admin->syncRoles(['Super Administrator']);
});

it('moves to the password step and remembers the email', function () {
    $this->post('/admin/login/identify', ['email' => 'staff@summitlegaluae.com'])
        ->assertRedirect();

    $this->get('/admin/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Auth/Login')
            ->where('step', 'password')
            ->where('email', 'staff@summitlegaluae.com')
        );
});

it('signs in on the second step', function () {
    $this->post('/admin/login/identify', ['email' => 'staff@summitlegaluae.com']);

    $this->post('/admin/login', [
        'email' => 'staff@summitlegaluae.com',
        'password' => 'CorrectHorse123!',
        'session_length' => 'standard',
    ])->assertRedirect(route('admin.dashboard'));

    expect(auth('admin')->check())->toBeTrue();
});

it('records the sign-in, so a first login is visible', function () {
    $this->post('/admin/login/identify', ['email' => 'staff@summitlegaluae.com']);
    $this->post('/admin/login', [
        'email' => 'staff@summitlegaluae.com',
        'password' => 'CorrectHorse123!',
        'session_length' => 'standard',
    ]);

    expect($this->admin->fresh()->last_login_at)->not->toBeNull();
});

it('reports an error rather than failing silently when the email is missing', function () {
    // This is the exact request the browser was sending. It must produce an
    // error the form can show, not a redirect that looks like nothing
    // happened.
    $this->post('/admin/login/identify', ['email' => 'staff@summitlegaluae.com']);

    $this->post('/admin/login', [
        'email' => '',
        'password' => 'CorrectHorse123!',
        'session_length' => 'standard',
    ])->assertSessionHasErrors('email');

    expect(auth('admin')->check())->toBeFalse();
});

it('shows every error on the password step, not only the password one', function () {
    // The template used to render errors.password alone, so a failure on any
    // other field produced a blank screen and a button that did nothing.
    $form = file_get_contents(resource_path('js/Pages/Admin/Auth/Login.vue'));

    expect($form)->toContain('v-for="(message, field) in login.errors"');
});

it('takes the email from the server on submit, never from stale form state', function () {
    // The component is reused between steps, so anything seeded at setup is
    // still the step-one value when step two submits.
    $form = file_get_contents(resource_path('js/Pages/Admin/Auth/Login.vue'));

    expect($form)
        ->toContain('email: props.email')
        ->not->toContain("useForm({ email: props.email ?? '', password:");
});

it('gives the same message whether or not the account exists', function () {
    foreach (['staff@summitlegaluae.com', 'nobody@example.invalid'] as $email) {
        $this->post('/admin/login/identify', ['email' => $email]);

        $response = $this->post('/admin/login', [
            'email' => $email,
            'password' => 'definitely-not-the-password',
            'session_length' => 'standard',
        ]);

        $response->assertSessionHasErrors(['password' => 'Those details do not match our records.']);
        $this->flushSession();
    }
});

it('advances on step one even for an address that does not exist', function () {
    // Step one must never reveal which addresses are Summit staff.
    $this->post('/admin/login/identify', ['email' => 'nobody@example.invalid'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('keeps the chosen session length', function () {
    $this->post('/admin/login/identify', ['email' => 'staff@summitlegaluae.com']);
    $this->post('/admin/login', [
        'email' => 'staff@summitlegaluae.com',
        'password' => 'CorrectHorse123!',
        'session_length' => 'extended',
    ]);

    expect(session('session_length'))->toBe('extended');
});
