<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Takes the Cookie Policy off the public site.
 *
 * The specification is explicit that it must not be published until the
 * production cookie scan is complete — the page names four categories and
 * counts the cookies in each, and publishing counts nobody has verified against
 * the live site is a statement about data collection that may not be true.
 *
 * This is a data migration rather than a seeder edit because the row already
 * exists as published on the live database, and reseeding content in production
 * is not something anybody should have to do to fix a compliance problem.
 *
 * The content is untouched. Republishing is one click on Content once the scan
 * is done, and the footer link returns on its own when it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pages')->where('key', 'cookies')->update([
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function down(): void
    {
        DB::table('pages')->where('key', 'cookies')->update([
            'is_published' => true,
            'published_at' => now(),
        ]);
    }
};
