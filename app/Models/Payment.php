<?php

namespace App\Models;

use App\Domain\Payments\Enums\PaymentStatus;
use App\Domain\Payments\Enums\PaymentType;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use RecordsActivity, SoftDeletes;

    protected array $logAttributes = ['status', 'type', 'total_amount', 'paid_at', 'method'];

    protected $fillable = [
        'case_id', 'gateway', 'gateway_reference', 'link_url', 'link_token',
        'amount', 'vat_amount', 'total_amount', 'currency', 'type', 'stage_label',
        'status', 'method', 'paid_at', 'failed_reason', 'raw_payload', 'created_by',
    ];

    protected $hidden = ['link_token', 'raw_payload'];

    /**
     * Every payment the platform raised before the type column existed was
     * Summit's own fee — it only ever raised one per case. A new instance
     * carries that same default so `type` is never null to reason about.
     */
    protected $attributes = ['type' => PaymentType::ProfessionalFee->value];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'type' => PaymentType::class,
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

    /** @param  Builder<Payment>  $query */
    public function scopeProfessionalFees($query)
    {
        return $query->where('type', PaymentType::ProfessionalFee);
    }

    /** @param  Builder<Payment>  $query */
    public function scopeDisbursements($query)
    {
        return $query->where('type', PaymentType::Disbursement);
    }

    public function isDisbursement(): bool
    {
        return $this->type === PaymentType::Disbursement;
    }

    public function isRefundable(): bool
    {
        return $this->status === PaymentStatus::Paid
            && $this->refunds()->where('status', 'completed')->doesntExist();
    }
}
