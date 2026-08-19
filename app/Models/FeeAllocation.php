<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;

/**
 * How the professional fee is apportioned across the stages of the work.
 * Band C refunds the portion allocated to stages not yet reached.
 */
class FeeAllocation extends Model
{
    use RecordsActivity;

    protected $fillable = ['service_type', 'stage', 'percentage'];

    protected function casts(): array
    {
        return ['percentage' => 'decimal:2'];
    }
}
