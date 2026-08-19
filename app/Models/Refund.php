<?php

namespace App\Models;

use App\Domain\Payments\Enums\RefundBand;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use RecordsActivity;

    protected $fillable = [
        'payment_id', 'band', 'amount', 'deduction_amount', 'deduction_reason',
        'calculation', 'status', 'processed_at', 'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'band' => RefundBand::class,
            'amount' => 'decimal:2',
            'deduction_amount' => 'decimal:2',
            'calculation' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
