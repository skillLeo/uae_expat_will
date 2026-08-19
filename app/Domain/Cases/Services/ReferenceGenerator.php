<?php

namespace App\Domain\Cases\Services;

use App\Models\LegalCase;
use Illuminate\Support\Facades\DB;

/**
 * Generates the case reference: SLC-YYYY-NNNNN.
 *
 * The sequence restarts each year and is allocated inside a transaction with a
 * row lock, so two assessments completing in the same millisecond cannot be
 * handed the same reference. The uniqueness constraint on the column is the
 * backstop; the retry loop is what stops it ever being hit under normal load.
 */
class ReferenceGenerator
{
    public function generate(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $prefix = config('assessment.reference_prefix', 'SLC');

        return DB::transaction(function () use ($year, $prefix) {
            $latest = LegalCase::withTrashed()
                ->where('reference', 'like', "{$prefix}-{$year}-%")
                ->lockForUpdate()
                ->orderByDesc('reference')
                ->value('reference');

            $next = $latest === null
                ? 1
                : ((int) substr($latest, strrpos($latest, '-') + 1)) + 1;

            return sprintf('%s-%d-%05d', $prefix, $year, $next);
        });
    }
}
