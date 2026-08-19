<?php

namespace Database\Seeders;

use App\Models\FeeAllocation;
use Illuminate\Database\Seeder;

/**
 * How the professional fee is apportioned across the stages of the work.
 *
 * Band C refunds the portion allocated to stages not yet reached, so these
 * percentages are what makes "the unused portion" a computable figure rather
 * than a negotiation. Editable in admin, because the split is commercial.
 */
class FeeAllocationSeeder extends Seeder
{
    public function run(): void
    {
        $allocations = [
            ['substantive_work_started', 25.00],
            ['first_draft_delivered', 40.00],
            ['final_approval', 20.00],
            ['authority_submitted', 15.00],
        ];

        foreach ($allocations as [$stage, $percentage]) {
            FeeAllocation::updateOrCreate(
                ['service_type' => 'standard_will', 'stage' => $stage],
                ['percentage' => $percentage],
            );
        }
    }
}
