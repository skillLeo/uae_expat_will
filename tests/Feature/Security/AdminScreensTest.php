<?php

use App\Domain\Cases\Enums\CaseStatus;
use App\Domain\Cases\Enums\InternalStatus;
use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\LegalCase;
use App\Models\NotificationTemplate;
use App\Models\Page;
use App\Models\Payment;
use App\Models\Questionnaire;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Database\Seeders\NotificationTemplateSeeder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    seedPlatform();
    seedQuestionnaire();
    $this->seed(ContentSeeder::class);
    $this->seed(NotificationTemplateSeeder::class);

    $customer = Customer::create(['full_name' => 'Test Customer', 'email' => 'c@example.com']);

    $this->case = LegalCase::create([
        'reference' => 'SLC-2026-09001',
        'customer_id' => $customer->id,
        'status' => CaseStatus::AcceptedPaymentRequired,
        'internal_status' => InternalStatus::PaymentLinkSent,
        'service_type' => 'standard_will',
        'quoted_amount' => 2199,
    ]);

    $this->payment = Payment::create([
        'case_id' => $this->case->id,
        'gateway' => 'manual',
        'amount' => 2199, 'vat_amount' => 109.95, 'total_amount' => 2308.95,
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $this->version = Questionnaire::screening()->publishedVersion();
});

function asAdmin(array $roles = ['Super Administrator']): User
{
    $user = adminUser($roles);
    test()->actingAs($user, 'admin')->withSession(['2fa.passed' => true]);

    return $user;
}

it('renders every admin screen for a super administrator', function () {
    asAdmin();

    $page = Page::first();
    $template = NotificationTemplate::first();
    $role = Role::where('guard_name', 'admin')->first();
    $user = User::admins()->first();

    $routes = [
        '/admin',
        '/admin/cases',
        '/admin/cases/'.$this->case->id,
        '/admin/payments',
        '/admin/content',
        '/admin/content/faqs',
        '/admin/content/'.$page->id,
        '/admin/questionnaire',
        '/admin/questionnaire/'.$this->version->id,
        '/admin/notifications',
        '/admin/notifications/'.$template->id,
        '/admin/settings',
        '/admin/settings/commercial',
        '/admin/settings/mail',
        '/admin/users',
        '/admin/users/'.$user->id,
        '/admin/roles',
        '/admin/roles/'.$role->id.'/preview',
        '/admin/audit',
        '/admin/consents',
        '/admin/analytics',
    ];

    foreach ($routes as $route) {
        $response = $this->get($route);

        expect($response->status())
            ->toBe(200, "GET {$route} returned {$response->status()}");
    }
});

it('blocks the integration settings groups without settings.integrations', function () {
    // Administrator holds settings.edit but NOT settings.integrations, so the
    // credential groups must be unreachable even by direct URL.
    asAdmin(['Administrator']);

    $this->get('/admin/settings/commercial')->assertOk();

    foreach (['mail', 'whatsapp', 'payment'] as $group) {
        $this->get("/admin/settings/{$group}")->assertForbidden();
    }
});

it('enforces the permission on every mutating admin route', function (string $method, string $uri, array $roles) {
    asAdmin($roles);

    $response = $this->{$method}($uri, []);

    expect($response->status())->toBe(403, "{$method} {$uri} was not blocked");
})->with(function () {
    // Read Only holds nothing that mutates.
    return [
        'change status' => ['post', '/admin/cases/1/status', ['Read Only']],
        'assign' => ['post', '/admin/cases/1/assign', ['Read Only']],
        'add note' => ['post', '/admin/cases/1/notes', ['Read Only']],
        'payment link' => ['post', '/admin/cases/1/payment-link', ['Read Only']],
        'manual payment' => ['post', '/admin/cases/1/manual-payment', ['Read Only']],
        'refund' => ['post', '/admin/payments/1/refund', ['Read Only']],
        'edit content' => ['patch', '/admin/content/1', ['Read Only']],
        'publish content' => ['post', '/admin/content/1/publish', ['Read Only']],
        'create draft' => ['post', '/admin/questionnaire/draft', ['Read Only']],
        'publish questionnaire' => ['post', '/admin/questionnaire/1/publish', ['Read Only']],
        'create role' => ['post', '/admin/roles', ['Administrator']],
        'invite user' => ['post', '/admin/users', ['Read Only']],
        'disable user' => ['post', '/admin/users/1/disable', ['Read Only']],
        'edit settings' => ['patch', '/admin/settings/commercial', ['Read Only']],
    ];
});

it('offers no route that could edit or delete an audit entry', function () {
    $routes = collect(app('router')->getRoutes())
        ->filter(fn ($r) => str_contains($r->uri(), 'audit'))
        ->map(fn ($r) => implode('|', $r->methods()).' '.$r->uri());

    foreach ($routes as $route) {
        expect($route)->not->toContain('DELETE')
            ->and($route)->not->toContain('PATCH')
            ->and($route)->not->toContain('PUT');
    }
});

it('lets a case handler see the case list but not the restricted body', function () {
    $handler = asAdmin(['Case Handler']);
    $this->case->update(['assigned_to' => $handler->id, 'is_restricted' => true]);

    $this->get('/admin/cases')->assertOk();

    $this->get('/admin/cases/'.$this->case->id)->assertInertia(
        fn ($page) => $page->where('readable', false)
            ->where('record.restricted_reason', null)
            ->where('answers', [])
    );
});

it('shows the restricted body to a legal reviewer', function () {
    asAdmin(['Legal Reviewer']);
    $this->case->update(['is_restricted' => true]);

    $this->get('/admin/cases/'.$this->case->id)->assertInertia(
        fn ($page) => $page->where('readable', true)
    );
});
