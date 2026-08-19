<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use RecordsActivity, SoftDeletes;

    protected $fillable = [
        'user_id', 'full_name', 'email', 'phone', 'nationality',
        'country_of_residence', 'emirate', 'preferred_contact_method', 'language_support',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(LegalCase::class, 'customer_id');
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->full_name))[0] ?? $this->full_name;
    }
}
