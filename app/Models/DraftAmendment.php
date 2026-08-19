<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DraftAmendment extends Model
{
    protected $fillable = ['draft_id', 'requested_by', 'body', 'is_within_allowance', 'status', 'resolved_at'];

    protected function casts(): array
    {
        return ['is_within_allowance' => 'boolean', 'resolved_at' => 'datetime'];
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(Draft::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
