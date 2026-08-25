<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Switches the live wordmark to the One Name direction.
 *
 * Summit chose it in August 2026. The previous treatment set UAE at 38% of the
 * name in the margin, where it read as a country tag on a product called "Expat
 * Wills" rather than as half the brand name.
 *
 * A migration rather than a seeder default, because SettingsSeeder::define()
 * deliberately never overwrites a value an administrator has set — which is
 * correct, and which also means a new default would never reach the live site.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'branding.wordmark_direction')
            ->update([
                'value' => 'one_name',
                'help_text' => 'one_name (live) · margin (1b) · engrossment (1a) · registers (1c)',
                'updated_at' => now(),
            ]);

        // The repository caches; a stale read here would show the old mark
        // until something else happened to bust it.
        Cache::flush();
    }

    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'branding.wordmark_direction')
            ->update(['value' => 'margin', 'updated_at' => now()]);
    }
};
