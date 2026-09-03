<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The GA4 property Summit created for the site, supplied 3 September 2026.
 *
 * A migration rather than a seeder edit, because define() only writes a
 * default when the row does not exist and a deploy runs migrations, not
 * seeders. Without this the value reaches a fresh install and nowhere else.
 *
 * Setting this also opens googletagmanager.com and google-analytics.com in the
 * Content-Security-Policy, which is built from these same settings — the CSP
 * admits those hosts only while a measurement ID is present.
 */
return new class extends Migration
{
    private const ID = 'G-2C8L9N60Z1';

    public function up(): void
    {
        $this->write(self::ID);
    }

    public function down(): void
    {
        $this->write('');
    }

    private function write(string $value): void
    {
        DB::table('settings')
            ->where('key', 'analytics.ga4_measurement_id')
            ->update(['value' => $value, 'updated_at' => now()]);

        DB::table('settings')
            ->where('key', 'analytics.ga4_measurement_id')
            ->update([
                'help_text' => 'Loads under Consent Mode: present on every page, but stores nothing until the visitor accepts analytics cookies.',
            ]);

        Cache::flush();
    }
};
