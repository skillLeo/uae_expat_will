<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A "this ran" marker, written by the thing that ran.
 *
 * The whole health panel rests on these: a heartbeat that stops arriving is the
 * only honest way to know a scheduled process has died, because a dead process
 * cannot report its own death.
 */
class SystemHeartbeat extends Model
{
    protected $fillable = ['key', 'ran_at', 'status', 'meta'];

    protected function casts(): array
    {
        return ['ran_at' => 'datetime', 'meta' => 'array'];
    }

    /** @param array<string, mixed> $meta */
    public static function beat(string $key, string $status = 'ok', array $meta = []): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['ran_at' => now(), 'status' => $status, 'meta' => $meta ?: null],
        );
    }

    public static function lastRun(string $key): ?self
    {
        return static::where('key', $key)->first();
    }
}
