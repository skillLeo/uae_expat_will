<?php

use App\Domain\Settings\Services\SettingsRepository;
use App\Domain\System\Actions\NotifyHealthTransitions;
use App\Domain\System\DTOs\HealthCheck;
use App\Domain\System\Enums\HealthState;
use App\Domain\System\Services\SystemHealth;
use App\Models\SystemHealthState;
use App\Models\SystemHeartbeat;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    seedPlatform();
    Cache::flush();
    fakeRenderer(200);
});

/**
 * Http::fake() appends stubs and the earliest match wins, so a test that needs
 * a different answer has to start from a clean factory rather than fake twice.
 */
function fakeRenderer(int $status): void
{
    app()->forgetInstance(Factory::class);
    Http::clearResolvedInstances();
    Http::fake(['*' => Http::response($status === 200 ? ['status' => 'OK'] : '', $status)]);
}

function checkFor(string $key): HealthCheck
{
    return collect(app(SystemHealth::class)->run())->firstWhere('key', $key);
}

// ------------------------------------------------------------------ scheduler

it('reports the scheduler as critical when it has never run', function () {
    // This is the exact scenario the panel exists for: the cron entry was
    // never added in the hosting panel.
    $check = checkFor('scheduler');

    expect($check->state)->toBe(HealthState::Critical)
        ->and($check->consequence)->toContain('Nothing scheduled is happening')
        ->and($check->fixIsHostPanel)->toBeTrue();
});

it('reports the scheduler as healthy on a recent beat', function () {
    SystemHeartbeat::beat('scheduler');

    expect(checkFor('scheduler')->state)->toBe(HealthState::Healthy);
});

it('escalates the scheduler as the beat ages', function (int $minutes, HealthState $expected) {
    SystemHeartbeat::create(['key' => 'scheduler', 'ran_at' => now()->subMinutes($minutes), 'status' => 'ok']);

    expect(checkFor('scheduler')->state)->toBe($expected);
})->with([
    'fresh' => [1, HealthState::Healthy],
    'slightly stale' => [10, HealthState::Warning],
    // Critical well before the 02:00 daily window would be missed.
    'stopped' => [45, HealthState::Critical],
]);

// ---------------------------------------------------------------------- queue

it('reports an empty queue as healthy', function () {
    expect(checkFor('queue')->state)->toBe(HealthState::Healthy);
});

it('treats an old waiting job as critical, not a warning', function () {
    // The worker drains every minute, so minutes of backlog means it is dead.
    DB::table('jobs')->insert([
        'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
        'reserved_at' => null, 'available_at' => now()->subMinutes(20)->timestamp,
        'created_at' => now()->subMinutes(20)->timestamp,
    ]);

    $check = checkFor('queue');

    expect($check->state)->toBe(HealthState::Critical)
        ->and($check->consequence)->toContain('nobody at Summit is being told about them');
});

it('does not panic over a job queued seconds ago', function () {
    DB::table('jobs')->insert([
        'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
        'reserved_at' => null, 'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    expect(checkFor('queue')->state)->toBe(HealthState::Healthy);
});

// --------------------------------------------------------------- failed jobs

it('surfaces failed jobs with their error inline', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
        'payload' => '{}', 'exception' => "RuntimeException: SMTP connect failed\n#0 stack...",
        'failed_at' => now(),
    ]);

    $check = checkFor('failed_jobs');

    expect($check->state)->toBe(HealthState::Warning)
        ->and($check->detail['recent'][0]['error'])->toContain('SMTP connect failed')
        // The stack trace does not belong on a dashboard.
        ->and($check->detail['recent'][0]['error'])->not->toContain('#0 stack');
});

// ----------------------------------------------------------------------- ssr

it('reports the renderer as critical when it is not answering', function () {
    fakeRenderer(500);

    $check = checkFor('ssr');

    expect($check->state)->toBe(HealthState::Critical)
        ->and($check->consequence)->toContain('search engines');
});

it('warns when the renderer is answering but serving an old build', function () {
    // The dangerous case: health checks pass perfectly while the site is stale.
    $marker = storage_path('app/ssr-started-at');
    file_put_contents($marker, (string) now()->subHour()->timestamp);
    touch(base_path('bootstrap/ssr/ssr.js'), now()->timestamp);

    $check = checkFor('ssr');

    expect($check->state)->toBe(HealthState::Warning)
        ->and($check->consequence)->toContain('previous version');

    @unlink($marker);
})->skip(fn () => ! file_exists(base_path('bootstrap/ssr/ssr.js')), 'SSR bundle not built');

// -------------------------------------------------------------- gateway/mail

it('treats test mode on production as critical', function () {
    app()->detectEnvironment(fn () => 'production');
    app(SettingsRepository::class)->setMany(['payment.test_mode' => true, 'payment.store_id' => 'abc']);

    $check = checkFor('gateway');

    expect($check->state)->toBe(HealthState::Critical)
        ->and($check->consequence)->toContain('No real payment can be taken');
});

it('accepts test mode outside production', function () {
    app(SettingsRepository::class)->setMany(['payment.test_mode' => true, 'payment.store_id' => 'abc']);

    expect(checkFor('gateway')->state)->toBe(HealthState::Healthy);
});

it('reports mail as critical with no host', function () {
    app(SettingsRepository::class)->set('mail.host', null);

    expect(checkFor('mail')->state)->toBe(HealthState::Critical);
});

it('warns when mail is configured but never tested', function () {
    app(SettingsRepository::class)->set('mail.host', 'smtp.example.com');

    $check = checkFor('mail');

    expect($check->state)->toBe(HealthState::Warning)
        ->and($check->consequence)->toContain('invisible until a customer says');
});

it('reports mail as critical when the last test failed', function () {
    app(SettingsRepository::class)->set('mail.host', 'smtp.example.com');
    SystemHeartbeat::beat('mail_test', 'failed', ['error' => 'Connection refused']);

    $check = checkFor('mail');

    expect($check->state)->toBe(HealthState::Critical)
        ->and($check->detail['error'])->toBe('Connection refused');
});

// ------------------------------------------------------------------ resilience

it('degrades a throwing check to unknown rather than breaking the dashboard', function () {
    // A broken monitor must never break the thing it monitors.
    Schema::drop('system_heartbeats');

    $check = checkFor('scheduler');

    expect($check->state)->toBe(HealthState::Unknown)
        ->and($check->summary)->toContain('could not run');
});

it('never reports unknown as healthy', function () {
    expect(HealthState::Unknown->severity())->toBeGreaterThan(HealthState::Healthy->severity())
        ->and(HealthState::Unknown->isAlertable())->toBeFalse();
});

it('caches so the dashboard stays cheap', function () {
    $health = app(SystemHealth::class);
    $health->checks();

    expect(Cache::has('system.health'))->toBeTrue();

    // A second read must not re-run the checks.
    DB::table('jobs')->insert([
        'queue' => 'default', 'payload' => '{}', 'attempts' => 0,
        'reserved_at' => null, 'available_at' => now()->subHour()->timestamp,
        'created_at' => now()->subHour()->timestamp,
    ]);

    expect(collect($health->checks())->firstWhere('key', 'queue')->state)->toBe(HealthState::Healthy)
        ->and(collect($health->checks(fresh: true))->firstWhere('key', 'queue')->state)->toBe(HealthState::Critical);
});

// ---------------------------------------------------------------------- alerts

it('emails once when a check first goes critical, and not again', function () {
    Mail::fake();
    app(SettingsRepository::class)->set('contact.email', 'ops@example.com');

    $checks = app(SystemHealth::class)->run();
    $notify = app(NotifyHealthTransitions::class);

    $first = $notify->execute($checks);
    expect($first)->not->toBeEmpty();

    // Still critical, but no longer a transition.
    $second = $notify->execute($checks);
    expect($second)->toBeEmpty();
});

it('records the state change so a recovery re-arms the alert', function () {
    Mail::fake();
    app(SettingsRepository::class)->set('contact.email', 'ops@example.com');
    $notify = app(NotifyHealthTransitions::class);

    $notify->execute(app(SystemHealth::class)->run());
    expect(SystemHealthState::where('check_key', 'scheduler')->value('state'))->toBe('critical');

    // Recover.
    SystemHeartbeat::beat('scheduler');
    $notify->execute(app(SystemHealth::class)->run());
    expect(SystemHealthState::where('check_key', 'scheduler')->value('state'))->toBe('healthy');
});

it('puts no case data in an alert', function () {
    $sent = [];
    Mail::shouldReceive('raw')->andReturnUsing(function (string $body) use (&$sent) {
        $sent[] = $body;
    });
    app(SettingsRepository::class)->set('contact.email', 'ops@example.com');

    app(NotifyHealthTransitions::class)->execute(app(SystemHealth::class)->run());

    expect($sent)->not->toBeEmpty();

    foreach ($sent as $body) {
        expect($body)->not->toMatch('/SLC-\d{4}-\d{5}/')
            ->and(strtolower($body))->not->toContain('customer')
            ->and(strtolower($body))->not->toContain('beneficiary')
            ->and(strtolower($body))->not->toContain('restricted');
    }
});

// ------------------------------------------------------------------ dashboard

it('shows the panel to a role that can act on it', function () {
    $user = adminUser(['Super Administrator']);
    $this->actingAs($user, 'admin')->withSession(['2fa.passed' => true]);

    $this->get('/admin')->assertInertia(fn ($page) => $page->has('health.checks'));
});

it('hides the panel from a role that cannot', function () {
    // A coordinator seeing "backups are failing" can do nothing but worry.
    $user = adminUser(['Case Handler']);
    $this->actingAs($user, 'admin')->withSession(['2fa.passed' => true]);

    $this->get('/admin')->assertInertia(fn ($page) => $page->where('health', null));
});

it('exits non-zero on critical so it can gate a deploy', function () {
    $this->artisan('system:health --no-alert')->assertExitCode(1);
});
