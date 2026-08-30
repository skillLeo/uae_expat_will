<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Puts the currency on the authority fee figures.
 *
 * The table stored "950.00" and "≈ 2,100.00" with no currency anywhere in the
 * row, so the pricing page showed a court's charge as a bare number while the
 * professional fee beside it read "AED 1,999". Ahmed asked for every price to
 * carry AED, the authority charges included.
 *
 * A migration rather than a seeder edit, because SettingsSeeder::define() only
 * writes a default when the row does not exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->write([
            ['route' => 'ADJD Civil Will', 'amount' => 'AED 950.00', 'note' => "For one regular Will, subject to ADJD's current service, eligibility and fee schedule"],
            ['route' => 'Dubai Courts Will', 'amount' => '≈ AED 2,100.00', 'note' => 'For one Will, subject to the service and fee confirmed by Dubai Courts'],
            ['route' => 'DIFC Courts Will', 'amount' => 'Varies by Will type', 'note' => 'Confirmed from the current DIFC fee schedule with the individual quotation'],
        ]);
    }

    public function down(): void
    {
        $this->write([
            ['route' => 'ADJD Civil Will', 'amount' => '950.00', 'note' => "For one regular Will, subject to ADJD's current service, eligibility and fee schedule"],
            ['route' => 'Dubai Courts Will', 'amount' => '≈ 2,100.00', 'note' => 'For one Will, subject to the service and fee confirmed by Dubai Courts'],
            ['route' => 'DIFC Courts Will', 'amount' => 'varies by Will type', 'note' => 'Confirmed from the current DIFC fee schedule with the individual quotation'],
        ]);
    }

    /** @param  array<int, array<string, string>>  $rows */
    private function write(array $rows): void
    {
        DB::table('settings')
            ->where('key', 'commercial.authority_fees')
            ->update(['value' => json_encode($rows), 'updated_at' => now()]);

        Cache::flush();
    }
};
