<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemHealthState extends Model
{
    protected $fillable = ['check_key', 'state', 'changed_at', 'notified_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime', 'notified_at' => 'datetime'];
    }
}
