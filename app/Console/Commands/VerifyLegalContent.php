<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Console\Command;

/**
 * Checks the seeded legal pages against the clause counts the master
 * specification fixes for each.
 *
 * This command DOES NOT and MUST NOT write or complete any legal wording. The
 * contract forbids altering Summit's legal content, so all it does is count
 * what is present and report what is short, for someone to take back to them.
 */
class VerifyLegalContent extends Command
{
    protected $signature = 'content:verify-legal';

    protected $description = 'Report any legal page whose seeded text is shorter than the specification requires';

    /**
     * Clause counts from Part 7.9 of the master specification.
     *
     * @var array<string, array{label: string, clauses: int, note: string|null}>
     */
    private const EXPECTED = [
        'terms' => ['label' => 'Terms and Conditions', 'clauses' => 25, 'note' => null],
        'privacy' => ['label' => 'Privacy Policy', 'clauses' => 18, 'note' => 'Dated 6 August — deliberately unchanged.'],
        'refund' => ['label' => 'Payment and Refund Policy', 'clauses' => 16, 'note' => 'Carries the stage-based refund engine.'],
        'disclaimer' => ['label' => 'Legal Disclaimer', 'clauses' => 17, 'note' => 'Carries the exact three-outcome customer-facing names.'],
        'cookies' => ['label' => 'Cookie Policy', 'clauses' => 9, 'note' => 'MUST NOT be published until the production cookie scan is complete.'],
    ];

    public function handle(): int
    {
        $rows = [];
        $short = 0;

        foreach (self::EXPECTED as $key => $expected) {
            $page = Page::where('key', $key)->first();

            if ($page === null) {
                $rows[] = [$expected['label'], $expected['clauses'], '—', 'PAGE MISSING'];
                $short++;

                continue;
            }

            $section = $page->sections()->where('key', 'clauses')->first();
            $actual = $section instanceof PageSection ? count($section->items ?? []) : 0;
            $deficit = $expected['clauses'] - $actual;

            if ($deficit > 0) {
                $short++;
            }

            $rows[] = [
                $expected['label'],
                $expected['clauses'],
                $actual,
                $deficit > 0 ? "SHORT BY {$deficit}" : 'complete',
            ];
        }

        $this->table(['Page', 'Expected clauses', 'Seeded', 'Status'], $rows);

        foreach (self::EXPECTED as $expected) {
            if ($expected['note']) {
                $this->line("  {$expected['label']}: {$expected['note']}");
            }
        }

        if ($short > 0) {
            $this->newLine();
            $this->warn("{$short} legal page(s) are short of the specification.");
            $this->line('  The missing wording is Summit\'s to supply. Do not draft or complete it here —');
            $this->line('  the contract forbids altering their legal content.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Every legal page matches the specification\'s clause count.');

        return self::SUCCESS;
    }
}
