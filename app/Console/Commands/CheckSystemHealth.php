<?php

namespace App\Console\Commands;

use App\Domain\System\Actions\NotifyHealthTransitions;
use App\Domain\System\Enums\HealthState;
use App\Domain\System\Services\SystemHealth;
use Illuminate\Console\Command;

class CheckSystemHealth extends Command
{
    protected $signature = 'system:health {--no-alert : Report only, do not email}';

    protected $description = 'Run every system health check and alert on anything newly critical';

    public function handle(SystemHealth $health, NotifyHealthTransitions $notify): int
    {
        $checks = $health->run();

        $rows = array_map(fn ($c) => [
            strtoupper($c->state->value),
            $c->label,
            $c->summary,
        ], $checks);

        $this->table(['State', 'Check', 'Summary'], $rows);

        foreach ($checks as $check) {
            if ($check->consequence && $check->state !== HealthState::Healthy) {
                $this->line("  <fg=yellow>{$check->label}</>: {$check->consequence}");
            }
        }

        if (! $this->option('no-alert')) {
            $alerted = $notify->execute($checks);

            if ($alerted !== []) {
                $this->warn('Alert sent for: '.implode(', ', $alerted));
            }
        }

        $worst = $health->worst($checks);

        // Non-zero on critical so this is usable as a deployment gate.
        return $worst === HealthState::Critical ? self::FAILURE : self::SUCCESS;
    }
}
