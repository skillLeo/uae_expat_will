<?php

namespace App\Console\Commands;

use App\Domain\Audit\Services\AuditLogger;
use App\Models\Assessment;
use App\Models\LegalCase;
use Illuminate\Console\Command;

/**
 * Applies the retention schedule and logs exactly what it deleted.
 *
 * Every period is a setting, not a constant, because only one of the four is
 * actually fixed by the specification (incomplete assessments, 30 days). The
 * other three are proposals awaiting Summit's confirmation — open item 08.
 */
class ApplyRetentionPolicy extends Command
{
    protected $signature = 'retention:apply {--dry-run : Report what would be deleted without deleting it}';

    protected $description = 'Delete records that have passed their retention period';

    public function handle(AuditLogger $audit): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deleted = [];

        // 1. Incomplete assessments. The one period the specification fixes.
        $days = (int) setting('retention.incomplete_assessment_days', 30);
        $query = Assessment::where('status', '!=', 'completed')
            ->where('created_at', '<', now()->subDays($days));

        $deleted['incomplete_assessments'] = $this->purge($query, $dryRun);

        // 2. Unsuccessful enquiries — cases closed as declined or cancelled.
        $days = (int) setting('retention.unsuccessful_enquiry_days', 365);
        $query = LegalCase::whereIn('internal_status', ['closed_declined', 'closed_cancelled'])
            ->where('closed_at', '<', now()->subDays($days));

        $deleted['unsuccessful_enquiries'] = $this->purge($query, $dryRun);

        // 3. Completed matters, kept in years rather than days.
        $years = (int) setting('retention.completed_file_years', 7);
        $query = LegalCase::where('internal_status', 'registered_and_delivered')
            ->where('closed_at', '<', now()->subYears($years));

        $deleted['completed_files'] = $this->purge($query, $dryRun);

        foreach ($deleted as $what => $count) {
            $this->line(sprintf('  %-26s %s%d', $what, $dryRun ? 'would delete ' : 'deleted ', $count));
        }

        if (! $dryRun && array_sum($deleted) > 0) {
            // What was deleted is itself an audit event. The log is append-only,
            // so this record survives the deletion it describes.
            $audit->log('retention', 'Retention policy applied', null, $deleted);
        }

        return self::SUCCESS;
    }

    private function purge($query, bool $dryRun): int
    {
        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->delete(); // soft delete — recoverable during the grace window
        }

        return $count;
    }
}
