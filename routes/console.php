<?php

use App\Console\Commands\ApplyRetentionPolicy;
use App\Console\Commands\CheckSystemHealth;
use App\Console\Commands\EscalateOverdueCases;
use App\Models\SystemHeartbeat;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled work
|--------------------------------------------------------------------------
| Times are Asia/Dubai — the application timezone — so "03:00" means 03:00 in
| the UAE rather than wherever the server happens to sit.
*/

/*
 * The scheduler's own pulse.
 *
 * Written every minute by the scheduler itself. It is the only honest way to
 * know the scheduler is alive — a process that has stopped cannot report that
 * it stopped, so the absence of a recent beat IS the signal. The health panel
 * reads this and nothing else for that check.
 */
Schedule::call(fn () => SystemHeartbeat::beat('scheduler'))
    ->everyMinute()
    ->name('scheduler-heartbeat')
    ->withoutOverlapping();

// Health checks. Emails once when something first goes critical.
Schedule::command(CheckSystemHealth::class)
    ->everyFiveMinutes()
    ->timezone('Asia/Dubai')
    ->onOneServer()
    ->withoutOverlapping();

// Retention. Runs nightly and logs exactly what it removed.
Schedule::command(ApplyRetentionPolicy::class)
    ->dailyAt('03:00')
    ->timezone('Asia/Dubai')
    ->onOneServer();

// Escalation. Runs every two hours; the command itself decides whether it is
// inside working hours and whether a case is due another alert.
Schedule::command(EscalateOverdueCases::class)
    ->everyTwoHours()
    ->timezone('Asia/Dubai')
    ->onOneServer()
    ->withoutOverlapping();

// Daily backups, with a monitored health check and old-backup cleanup.
Schedule::command('backup:clean')->dailyAt('02:00')->timezone('Asia/Dubai');
Schedule::command('backup:run')->dailyAt('02:30')->timezone('Asia/Dubai');
Schedule::command('backup:monitor')->dailyAt('04:00')->timezone('Asia/Dubai');

// Queue upkeep.
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('auth:clear-resets')->daily();
