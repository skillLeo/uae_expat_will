<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Moves the live professional fee to the figure Summit approved.
 *
 * The same trap as the wordmark, and worth stating plainly because it will
 * catch the next person too: SettingsSeeder::define() writes a default only
 * when the row does not exist. That is deliberate — reseeding must never undo
 * an administrator's setting — but it also means changing a default in the
 * seeder has NO effect on a database that has already been seeded.
 *
 * So a price change is always a migration, never a seeder edit.
 */
return new class extends Migration
{
    private const FEES = [
        'commercial.standard_fee' => '1999',
        'commercial.mirror_fee' => '2999',
    ];

    public function up(): void
    {
        foreach (self::FEES as $key => $value) {
            DB::table('settings')->where('key', $key)->update([
                'value' => $value,
                'updated_at' => now(),
            ]);
        }

        Cache::flush();
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'commercial.standard_fee')->update([
            'value' => '2199',
            'updated_at' => now(),
        ]);

        Cache::flush();
    }
};
