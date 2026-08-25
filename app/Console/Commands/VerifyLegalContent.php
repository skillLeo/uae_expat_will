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

    protected $description = 'Report any legal page that no longer matches what Summit has accepted';

    /**
     * Clause counts, as accepted by Summit.
     *
     * `clauses` is what the page must have. `spec` is what Part 7.9 of the
     * master specification originally asked for, kept because the two used to
     * differ and somebody will eventually ask why.
     *
     * On 26 August 2026 Ahmed Mohammadi read the Privacy Policy, the Payment
     * and Refund Policy and the Legal Disclaimer and accepted them at nine
     * clauses each — "all legal pages i find it fine for now, so lets keep it
     * as is". He is the lawyer and it is his wording; that is his call to make
     * and it is recorded here rather than quietly written down to match.
     *
     * If Summit later supplies the remaining clauses, raise `clauses` back to
     * `spec` and this command starts guarding the fuller version again.
     *
     * `published` is what the page's is_published column MUST be. The Cookie
     * Policy is the one that must be false: it states cookie counts nobody has
     * verified against the live site yet, so publishing it is a statement about
     * data collection that may not be true.
     *
     * @var array<string, array{label: string, clauses: int, spec: int, published: bool, note: string|null}>
     */
    private const EXPECTED = [
        'terms' => ['label' => 'Terms and Conditions', 'clauses' => 25, 'spec' => 25, 'published' => true, 'note' => null],
        'privacy' => ['label' => 'Privacy Policy', 'clauses' => 9, 'spec' => 18, 'published' => true, 'note' => 'Accepted at 9 by Summit, 26 Aug 2026. Specification asked for 18.'],
        'refund' => ['label' => 'Payment and Refund Policy', 'clauses' => 9, 'spec' => 16, 'published' => true, 'note' => 'Accepted at 9 by Summit, 26 Aug 2026. Specification asked for 16.'],
        'disclaimer' => ['label' => 'Legal Disclaimer', 'clauses' => 9, 'spec' => 17, 'published' => true, 'note' => 'Accepted at 9 by Summit, 26 Aug 2026. Specification asked for 17.'],
        'cookies' => ['label' => 'Cookie Policy', 'clauses' => 9, 'spec' => 9, 'published' => false, 'note' => 'Withheld until the production cookie scan is complete. Republish from Content.'],
    ];

    public function handle(): int
    {
        $rows = [];
        $short = 0;

        foreach (self::EXPECTED as $key => $expected) {
            $page = Page::where('key', $key)->first();

            if ($page === null) {
                $rows[] = [$expected['label'], $expected['clauses'], '—', '—', 'PAGE MISSING'];
                $short++;

                continue;
            }

            $section = $page->sections()->where('key', 'clauses')->first();
            $actual = $section instanceof PageSection ? count($section->items ?? []) : 0;
            $deficit = $expected['clauses'] - $actual;

            if ($deficit > 0) {
                $short++;
            }

            $wrongState = $page->is_published !== $expected['published'];

            if ($wrongState) {
                $short++;
            }

            $rows[] = [
                $expected['label'],
                $expected['clauses'].($expected['spec'] !== $expected['clauses'] ? ' ('.$expected['spec'].')' : ''),
                $actual,
                $page->is_published ? 'live' : 'withheld',
                match (true) {
                    $wrongState && $expected['published'] => 'NOT PUBLISHED',
                    $wrongState => 'MUST BE WITHHELD',
                    $deficit > 0 => "SHORT BY {$deficit}",
                    default => 'complete',
                },
            ];
        }

        $this->table(['Page', 'Accepted (spec)', 'Seeded', 'On site', 'Status'], $rows);

        foreach (self::EXPECTED as $expected) {
            if ($expected['note']) {
                $this->line("  {$expected['label']}: {$expected['note']}");
            }
        }

        if ($short > 0) {
            $this->newLine();
            $this->warn("{$short} legal page(s) do not match what Summit accepted.");
            $this->line('  Any wording change is Summit\'s to supply. Do not draft or complete it here —');
            $this->line('  the contract forbids altering their legal content.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Every legal page matches what Summit has accepted.');

        return self::SUCCESS;
    }
}
