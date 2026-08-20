<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedView extends Model
{
    protected $fillable = ['user_id', 'name', 'resource', 'filters', 'is_shared'];

    protected function casts(): array
    {
        return ['filters' => 'array', 'is_shared' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Views this user can see: their own, plus anything shared with the team. */
    public function scopeAvailableTo(Builder $query, User $user, string $resource = 'cases'): Builder
    {
        return $query->where('resource', $resource)
            ->where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhere('is_shared', true))
            ->orderBy('name');
    }
}
