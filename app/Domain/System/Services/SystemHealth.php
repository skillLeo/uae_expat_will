<?php

namespace App\Domain\System\Services;

use App\Domain\System\DTOs\HealthCheck;
use App\Domain\System\Enums\HealthState;
use App\Models\SystemHeartbeat;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The platform's own vital signs.
 *
 * This exists because of one specific failure mode. The host does not allow
 * crontab over SSH, so the three cron entries are added by hand through hPanel.
 * If they are never added — or if they stop months from now — the platform
 * fails silently: the queue stops, every notification queues forever, a case is
 * created and Summit is never told it exists, and the first-contact countdown
 * goes overdue on a matter nobody has seen. Nothing in the interface would say
 * a word.
 *
 * So every check here answers "is this actually working", and every critical
 * state says what it means for the business rather than what broke technically.
 */
class SystemHealth
{
    /** Long enough to keep the dashboard cheap, short enough to notice a stall. */
    private const CACHE_SECONDS = 60;

    private const CACHE_KEY = 'system.health';

    /** @return array<int, HealthCheck> */
    public function checks(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        // The cache holds arrays, not objects, so a deploy that changes the DTO
        // cannot leave unreadable entries behind.
        $cached = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_SECONDS,
            fn () => array_map(fn (HealthCheck $c) => $c->toArray(), $this->run()),
        );

        return array_map(fn (array $c) => $this->hydrate($c), $cached);
    }

    /** @return array<int, HealthCheck> */
    public function run(): array
    {
        return [
            $this->guard('scheduler', 'Scheduled tasks', fn () => $this->scheduler()),
            $this->guard('queue', 'Notification queue', fn () => $this->queue()),
            $this->guard('failed_jobs', 'Failed jobs', fn () => $this->failedJobs()),
            $this->guard('ssr', 'Page rendering', fn () => $this->ssr()),
            $this->guard('backups', 'Backups', fn () => $this->backups()),
            $this->guard('retention', 'Data retention', fn () => $this->retention()),
            $this->guard('gateway', 'Payment gateway', fn () => $this->gateway()),
            $this->guard('mail', 'Outgoing email', fn () => $this->mail()),
        ];
    }

    /** The worst state across every check. */
    public function worst(?array $checks = null): HealthState
    {
        $checks ??= $this->checks();

        return collect($checks)
            ->map(fn (HealthCheck $c) => $c->state)
            ->sortByDesc(fn (HealthState $s) => $s->severity())
            ->first() ?? HealthState::Unknown;
    }

    /**
     * A check that throws degrades to "cannot tell" rather than taking the
     * dashboard down with it. A broken monitor must never break the thing it
     * monitors.
     */
    private function guard(string $key, string $label, callable $check): HealthCheck
    {
        try {
            return $check();
        } catch (Throwable $e) {
            return HealthCheck::unknown($key, $label, class_basename($e).': '.$e->getMessage());
        }
    }

    // ----------------------------------------------------------------- checks

    private function scheduler(): HealthCheck
    {
        $beat = SystemHeartbeat::lastRun('scheduler');

        if ($beat === null) {
            return new HealthCheck(
                key: 'scheduler', label: 'Scheduled tasks', state: HealthState::Critical,
                summary: 'The scheduler has never run.',
                detail: ['last run' => 'never'],
                consequence: 'Nothing scheduled is happening: no backups, no data-retention clean-up, and no overdue-case escalations. This normally means the cron entry was never added.',
                fix: 'Add the schedule:run cron entry in hPanel. The exact line is in DEPLOYMENT.md.',
                fixIsHostPanel: true,
            );
        }

        $minutes = $beat->ran_at->diffInMinutes(now());

        // Daily work runs at 02:00. Critical well before that window is missed.
        [$state, $summary] = match (true) {
            $minutes >= 30 => [HealthState::Critical, "Last ran {$beat->ran_at->diffForHumans()}."],
            $minutes >= 5 => [HealthState::Warning, "Last ran {$beat->ran_at->diffForHumans()}."],
            default => [HealthState::Healthy, "Ran {$beat->ran_at->diffForHumans()}."],
        };

        return new HealthCheck(
            key: 'scheduler', label: 'Scheduled tasks', state: $state, summary: $summary,
            detail: ['last run' => $beat->ran_at->toDayDateTimeString(), 'expected' => 'every minute'],
            consequence: $state === HealthState::Healthy ? null
                : 'Backups, data-retention clean-up and overdue-case escalations have stopped running.',
            fix: $state === HealthState::Healthy ? null
                : 'Check the schedule:run cron entry in hPanel. See DEPLOYMENT.md.',
            fixIsHostPanel: $state !== HealthState::Healthy,
        );
    }

    private function queue(): HealthCheck
    {
        $pending = DB::table('jobs')->count();
        $oldest = DB::table('jobs')->min('created_at');

        $ageMinutes = null;

        if ($oldest !== null) {
            // The jobs table stores created_at as an integer timestamp.
            $created = is_numeric($oldest)
                ? Carbon::createFromTimestamp((int) $oldest)
                : Carbon::parse($oldest);
            $ageMinutes = $created->diffInMinutes(now());
        }

        // The worker drains the queue every minute, so anything sitting for
        // minutes means it is not running at all.
        [$state, $summary] = match (true) {
            $pending === 0 => [HealthState::Healthy, 'Nothing waiting.'],
            $ageMinutes >= 5 => [HealthState::Critical, "{$pending} waiting, oldest {$ageMinutes} minutes."],
            $ageMinutes >= 2 => [HealthState::Warning, "{$pending} waiting, oldest {$ageMinutes} minutes."],
            default => [HealthState::Healthy, "{$pending} waiting, all recent."],
        };

        return new HealthCheck(
            key: 'queue', label: 'Notification queue', state: $state, summary: $summary,
            detail: [
                'waiting' => $pending,
                'oldest' => $ageMinutes === null ? '—' : $ageMinutes.' min',
            ],
            consequence: $state === HealthState::Critical
                ? 'Emails and WhatsApp messages are not being delivered. New enquiries are being recorded, but nobody at Summit is being told about them, and customers are not receiving their result or their receipt.'
                : ($state === HealthState::Warning ? 'Messages are being delayed.' : null),
            fix: $state === HealthState::Healthy ? null
                : 'Check the queue-tick.sh cron entry in hPanel. See DEPLOYMENT.md.',
            fixIsHostPanel: $state !== HealthState::Healthy,
        );
    }

    private function failedJobs(): HealthCheck
    {
        $count = DB::table('failed_jobs')->count();

        $recent = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(5)
            ->get(['uuid', 'queue', 'exception', 'failed_at'])
            ->map(fn ($row) => [
                // First line only: a full stack trace is not dashboard material.
                'error' => str(strtok((string) $row->exception, "\n"))->limit(140)->toString(),
                'when' => (string) $row->failed_at,
            ])
            ->all();

        [$state, $summary] = match (true) {
            $count === 0 => [HealthState::Healthy, 'None.'],
            $count >= 5 => [HealthState::Critical, "{$count} jobs have failed."],
            default => [HealthState::Warning, "{$count} ".($count === 1 ? 'job has' : 'jobs have').' failed.'],
        };

        return new HealthCheck(
            key: 'failed_jobs', label: 'Failed jobs', state: $state, summary: $summary,
            detail: ['failed' => $count, 'recent' => $recent],
            consequence: $count > 0
                ? 'Each failed job is a message that was never delivered. The customer or the team did not receive it, and it will not retry on its own.'
                : null,
            fix: $count > 0 ? 'Read the errors below, fix the cause, then run queue:retry all.' : null,
        );
    }

    private function ssr(): HealthCheck
    {
        $url = rtrim((string) config('inertia.ssr.url', 'http://127.0.0.1:13714'), '/');

        $reachable = false;

        try {
            $reachable = Http::timeout(2)->get($url.'/health')->successful();
        } catch (Throwable) {
            $reachable = false;
        }

        if (! $reachable) {
            return new HealthCheck(
                key: 'ssr', label: 'Page rendering', state: HealthState::Critical,
                summary: 'The renderer is not answering.',
                detail: ['address' => $url],
                consequence: 'Public pages are being assembled in the visitor\'s browser instead of on the server. People still see the site, but search engines and anyone without JavaScript see an empty page.',
                fix: 'Run ssr-watchdog.sh, and check its cron entry in hPanel. See DEPLOYMENT.md.',
                fixIsHostPanel: true,
            );
        }

        // Reachable is not the same as current. This host has a known failure
        // where a renderer started before the last build keeps answering health
        // checks perfectly while serving the previous version of the site.
        $bundle = base_path('bootstrap/ssr/ssr.js');
        $marker = storage_path('app/ssr-started-at');

        if (! File::exists($bundle) || ! File::exists($marker)) {
            return new HealthCheck(
                key: 'ssr', label: 'Page rendering', state: HealthState::Healthy,
                summary: 'Answering.',
                detail: ['address' => $url, 'freshness' => 'not recorded'],
            );
        }

        $startedAt = (int) trim((string) File::get($marker));
        $builtAt = File::lastModified($bundle);
        $stale = $builtAt > $startedAt;

        return new HealthCheck(
            key: 'ssr', label: 'Page rendering', state: $stale ? HealthState::Warning : HealthState::Healthy,
            summary: $stale ? 'Answering, but serving an old build.' : 'Answering, and current.',
            detail: [
                'started' => date('D j M H:i', $startedAt),
                'build' => date('D j M H:i', $builtAt),
            ],
            consequence: $stale
                ? 'Visitors are seeing the previous version of the site. Recent content or design changes are not live even though they were deployed.'
                : null,
            fix: $stale ? 'Run ssr-watchdog.sh to restart the renderer against the current build.' : null,
        );
    }

    private function backups(): HealthCheck
    {
        $name = (string) config('backup.backup.name', config('app.name'));
        $disk = Storage::disk(config('backup.backup.destination.disks')[0] ?? 'local');

        $files = collect($disk->files($name))
            ->filter(fn (string $f) => str_ends_with($f, '.zip'));

        if ($files->isEmpty()) {
            return new HealthCheck(
                key: 'backups', label: 'Backups', state: HealthState::Critical,
                summary: 'No backup has ever been taken.',
                detail: ['location' => $name],
                consequence: 'If the database were lost today, every case, every set of instructions and every uploaded document would be unrecoverable.',
                fix: 'Backups run from the scheduler — fix that first, then run backup:run once to confirm.',
                fixIsHostPanel: true,
            );
        }

        $latest = $files->sortByDesc(fn (string $f) => $disk->lastModified($f))->first();
        $when = Carbon::createFromTimestamp($disk->lastModified($latest));
        $hours = $when->diffInHours(now());

        [$state, $summary] = match (true) {
            $hours >= 168 => [HealthState::Critical, "Last backup {$when->diffForHumans()}."],
            $hours >= 48 => [HealthState::Warning, "Last backup {$when->diffForHumans()}."],
            default => [HealthState::Healthy, "Last backup {$when->diffForHumans()}."],
        };

        return new HealthCheck(
            key: 'backups', label: 'Backups', state: $state, summary: $summary,
            detail: [
                'taken' => $when->toDayDateTimeString(),
                'size' => $this->humanBytes($disk->size($latest)),
                'kept' => $files->count(),
            ],
            consequence: $state === HealthState::Healthy ? null
                : 'Anything entered since that date would be lost if the database failed.',
            fix: $state === HealthState::Healthy ? null : 'Backups run from the scheduler — check that first.',
            fixIsHostPanel: $state !== HealthState::Healthy,
        );
    }

    private function retention(): HealthCheck
    {
        $beat = SystemHeartbeat::lastRun('retention');

        if ($beat === null) {
            return new HealthCheck(
                key: 'retention', label: 'Data retention', state: HealthState::Warning,
                summary: 'Has never run.',
                detail: ['last run' => 'never'],
                consequence: 'Records that should have been deleted under the retention policy are still being held. This is a commitment in the privacy policy.',
                fix: 'Retention runs from the scheduler — fix that first.',
                fixIsHostPanel: true,
            );
        }

        $days = $beat->ran_at->diffInDays(now());

        [$state, $summary] = match (true) {
            $days >= 7 => [HealthState::Critical, "Last ran {$beat->ran_at->diffForHumans()}."],
            $days >= 2 => [HealthState::Warning, "Last ran {$beat->ran_at->diffForHumans()}."],
            default => [HealthState::Healthy, "Ran {$beat->ran_at->diffForHumans()}."],
        };

        return new HealthCheck(
            key: 'retention', label: 'Data retention', state: $state, summary: $summary,
            detail: [
                'last run' => $beat->ran_at->toDayDateTimeString(),
                'removed then' => collect($beat->meta ?? [])->sum(),
            ],
            consequence: $state === HealthState::Healthy ? null
                : 'Records past their retention period are still being held, against what the privacy policy promises.',
            fix: $state === HealthState::Healthy ? null : 'Retention runs from the scheduler — check that first.',
            fixIsHostPanel: $state !== HealthState::Healthy,
        );
    }

    private function gateway(): HealthCheck
    {
        $testMode = (bool) setting('payment.test_mode', true);
        $configured = filled(setting('payment.store_id'));
        $isProduction = app()->environment('production');

        if ($testMode && $isProduction) {
            return new HealthCheck(
                key: 'gateway', label: 'Payment gateway', state: HealthState::Critical,
                summary: 'Still in test mode on the live site.',
                detail: ['mode' => 'test', 'credentials' => $configured ? 'set' : 'not set'],
                consequence: 'No real payment can be taken. Customers reaching checkout will not be charged, and the matter will never move past the payment stage.',
                fix: 'Enter the live store ID and authentication key under Settings, then turn test mode off and use the test-connection button.',
            );
        }

        if (! $configured) {
            return new HealthCheck(
                key: 'gateway', label: 'Payment gateway', state: HealthState::Warning,
                summary: 'No credentials entered.',
                detail: ['mode' => $testMode ? 'test' : 'live'],
                consequence: 'Payment links cannot be generated, so an accepted matter cannot be invoiced.',
                fix: 'Add the store ID and authentication key under Settings.',
            );
        }

        return new HealthCheck(
            key: 'gateway', label: 'Payment gateway', state: HealthState::Healthy,
            summary: $testMode ? 'Test mode (not a live environment).' : 'Live.',
            detail: ['mode' => $testMode ? 'test' : 'live', 'provider' => (string) setting('payment.gateway', 'telr')],
        );
    }

    private function mail(): HealthCheck
    {
        $host = setting('mail.host');

        if (blank($host)) {
            return new HealthCheck(
                key: 'mail', label: 'Outgoing email', state: HealthState::Critical,
                summary: 'No mail server configured.',
                detail: ['host' => 'not set'],
                consequence: 'The platform cannot send anything: no assessment results, no receipts, no questionnaire links, and no alerts to the team.',
                fix: 'Enter the SMTP details under Settings, then use the send-test button.',
            );
        }

        $test = SystemHeartbeat::lastRun('mail_test');

        if ($test === null) {
            return new HealthCheck(
                key: 'mail', label: 'Outgoing email', state: HealthState::Warning,
                summary: 'Configured, but never tested.',
                detail: ['host' => (string) $host],
                consequence: 'We do not know whether email actually leaves the server. A wrong password here is invisible until a customer says they never received anything.',
                fix: 'Use the send-test button under Settings to confirm.',
            );
        }

        $failed = $test->status !== 'ok';

        return new HealthCheck(
            key: 'mail', label: 'Outgoing email', state: $failed ? HealthState::Critical : HealthState::Healthy,
            summary: $failed
                ? 'The last test send failed.'
                : "Last test send succeeded {$test->ran_at->diffForHumans()}.",
            detail: array_filter([
                'host' => (string) $host,
                'last test' => $test->ran_at->toDayDateTimeString(),
                'error' => $failed ? (string) ($test->meta['error'] ?? 'unknown') : null,
            ]),
            consequence: $failed
                ? 'Email is not leaving the server. Customers are not receiving their results, receipts or questionnaire links.'
                : null,
            fix: $failed ? 'Check the SMTP details under Settings and send another test.' : null,
        );
    }

    // ----------------------------------------------------------------- helpers

    private function hydrate(array $c): HealthCheck
    {
        return new HealthCheck(
            key: $c['key'],
            label: $c['label'],
            state: HealthState::from($c['state']),
            summary: $c['summary'],
            detail: $c['detail'] ?? [],
            consequence: $c['consequence'] ?? null,
            fix: $c['fix'] ?? null,
            fixIsHostPanel: $c['fix_is_host_panel'] ?? false,
        );
    }

    private function humanBytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }
}
