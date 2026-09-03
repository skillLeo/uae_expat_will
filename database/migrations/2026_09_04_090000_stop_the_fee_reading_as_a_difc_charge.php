<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The note under the authority-charge table no longer prints the professional
 * fee as a bare number.
 *
 * That table's last row is DIFC Courts Will, and this note renders immediately
 * beneath it. So the page read:
 *
 *     DIFC Courts Will      Varies by Will type
 *     ------------------------------------------
 *     These figures are not part of the AED 10,000 professional fee...
 *
 * Summit read that as the DIFC charge being AED 10,000 and said so directly:
 * "you added the 10,000 and 15,000, which is the DIFC charge". They were right
 * that it reads that way. The sentence was technically accurate and
 * token-driven, which is exactly why nobody caught it: there was no wrong
 * number to find, only a number in the wrong place.
 *
 * It also named the single fee alone, which is untrue for anyone buying mirror
 * Wills. Both figures live on the cards at the top of the same page, so the
 * note now points there instead of restating one of them.
 *
 * A migration rather than re-running ContentSeeder, because that seeder deletes
 * and rebuilds every section on the page and would discard anything Summit has
 * edited in the admin since.
 */
return new class extends Migration
{
    private const NEW = 'These are the authority\'s own charges, set and collected by the authority. They are separate from our professional fee shown above, and are not a promise that a particular route will apply. Before payment of our professional fee we identify the known categories and the current estimates available. After legal review confirms the recommended route, we explain the then-current authority fee before asking you to authorise or make that payment.';

    private const OLD = 'These figures are not part of the AED {fee} professional fee and are not a promise that a particular route will apply. Before payment of our professional fee we identify the known categories and the current estimates available. After legal review confirms the recommended route, we explain the then-current authority fee before asking you to authorise or make that payment.';

    public function up(): void
    {
        $this->write(self::NEW);
    }

    public function down(): void
    {
        $this->write(self::OLD);
    }

    private function write(string $note): void
    {
        $section = DB::table('page_sections')->where('key', 'authority_fees')->first();

        if ($section === null) {
            return;
        }

        $settings = json_decode((string) $section->settings, true) ?: [];
        $settings['note'] = $note;

        DB::table('page_sections')->where('id', $section->id)->update([
            'settings' => json_encode($settings),
            'updated_at' => now(),
        ]);

        Cache::flush();
    }
};
