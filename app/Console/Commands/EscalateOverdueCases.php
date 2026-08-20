<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Models\LegalCase;
use App\Models\NotificationLog;
use Illuminate\Console\Command;

/**
 * Escalates a case whose first-contact countdown has passed.
 *
 * Repeats every two hours during working hours until somebody logs a contact,
 * which is what clears the countdown. Capped at three escalations, because an
 * alert that never stops is an alert people learn to ignore.
 *
 * THE ESCALATION NAMES THE COUNTDOWN AND THE REFERENCE, AND NOTHING ELSE. No
 * client circumstance, no answer and no restricted flag appears in one — the
 * same rule as the new-lead alert, for the same reason: these land on a phone
 * that somebody else may be looking at.
 */
class EscalateOverdueCases extends Command
{
    protected $signature = 'cases:escalate {--dry-run : Report what would be sent without sending it}';

    protected $description = 'Alert Summit about cases whose first-contact countdown has been breached';

    private const MAX_ESCALATIONS = 3;

    private const REPEAT_HOURS = 2;

    public function handle(NotificationDispatcher $dispatcher): int
    {
        if (! $this->withinWorkingHours()) {
            $this->line('Outside working hours. Nothing sent.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $sent = 0;

        $overdue = LegalCase::withoutGlobalScopes()
            ->overdue()
            ->with('customer:id,full_name', 'assignee:id,name')
            ->get();

        foreach ($overdue as $case) {
            $already = NotificationLog::where('case_id', $case->id)
                ->where('template_key', 'internal_countdown_overdue')
                ->count();

            if ($already >= self::MAX_ESCALATIONS) {
                continue;
            }

            $last = NotificationLog::where('case_id', $case->id)
                ->where('template_key', 'internal_countdown_overdue')
                ->latest()->first();

            if ($last && $last->created_at->diffInHours(now()) < self::REPEAT_HOURS) {
                continue;
            }

            $overdueBy = $case->countdown_due_at->diffForHumans(now(), ['parts' => 2, 'short' => true, 'syntax' => true]);

            $data = [
                'reference' => $case->reference,
                'countdown' => 'First contact',
                'overdue_by' => $overdueBy,
                'owner' => $case->assignee?->name ?? 'Unassigned',
                'escalation' => ($already + 1).' of '.self::MAX_ESCALATIONS,
            ];

            $this->line(sprintf(
                '  %s · overdue %s · owner %s · escalation %s',
                $case->reference, $overdueBy, $data['owner'], $data['escalation'],
            ));

            if ($dryRun) {
                continue;
            }

            foreach ($this->recipients() as $number) {
                $dispatcher->send('internal_countdown_overdue', NotificationChannel::Whatsapp, $number, $data, $case);
            }

            if ($inbox = setting('contact.email')) {
                $dispatcher->send('internal_countdown_overdue', NotificationChannel::Email, $inbox, $data, $case);
            }

            $sent++;
        }

        $this->info(sprintf(
            '%d overdue case(s); %s',
            $overdue->count(),
            $dryRun ? 'dry run, nothing sent' : "{$sent} escalated",
        ));

        return self::SUCCESS;
    }

    /**
     * Working hours in the application timezone.
     * Sunday to Thursday is the UAE working week.
     */
    private function withinWorkingHours(): bool
    {
        $now = now();

        return $now->hour >= 9
            && $now->hour < 18
            && ! in_array($now->dayOfWeek, [5, 6], true);
    }

    /** @return array<int, string> */
    private function recipients(): array
    {
        return array_values(array_filter([
            setting('whatsapp.admin_number_1'),
            setting('whatsapp.admin_number_2'),
        ]));
    }
}
