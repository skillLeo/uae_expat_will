<?php

namespace App\Models;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use RecordsActivity, SoftDeletes;

    protected array $logAttributes = ['status', 'total_amount', 'paid_at', 'method'];

    protected $fillable = [
        'case_id', 'gateway', 'gateway_reference', 'link_url', 'link_token',
        'amount', 'vat_amount', 'total_amount', 'currency', 'stage_label',
        'status', 'method', 'paid_at', 'failed_reason', 'raw_payload', 'created_by',
    ];

    protected $hidden = ['link_token', 'raw_payload'];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->latest('occurred_at');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRefundable(): bool
    {
        return $this->status === PaymentStatus::Paid
            && $this->refunds()->where('status', 'completed')->doesntExist();
    }
}
