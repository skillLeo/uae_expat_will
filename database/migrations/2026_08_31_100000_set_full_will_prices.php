<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The professional fees Summit set on 31 August 2026.
 *
 * AED 10,000 for a single Will, AED 15,000 for a mirror pair. With VAT at 5%
 * that is 10,500.00 and 15,750.00 at checkout.
 *
 * Nothing else has to change. Since the fee was single-sourced in August, the
 * pricing page, the FAQ answers, Terms clause 12, Refund clause 2, both result
 * screens, the receipt email and the assessment hero all read these two values
 * through a token — which is exactly the situation this arrangement was built
 * for.
 *
 * A migration rather than a seeder edit: define() only writes a default when
 * the row does not exist, so a changed default never reaches a live database.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->write(['commercial.standard_fee' => '10000', 'commercial.mirror_fee' => '15000']);
    }

    public function down(): void
    {
        $this->write(['commercial.standard_fee' => '1999', 'commercial.mirror_fee' => '2999']);
    }

    /** @param  array<string, string>  $fees */
    private function write(array $fees): void
    {
        foreach ($fees as $key => $value) {
            DB::table('settings')->where('key', $key)->update([
                'value' => $value,
                'updated_at' => now(),
            ]);
        }

        Cache::flush();
    }
};
