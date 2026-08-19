<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, RecordsActivity, SoftDeletes;

    protected array $logAttributes = ['name', 'email', 'is_active', 'disabled_at', 'user_type'];

    protected $fillable = [
        'user_type', 'name', 'email', 'phone', 'password', 'is_active',
        'timezone', 'locale',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'disabled_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
        ];
    }

    // ------------------------------------------------------------------ guards

    public const TYPE_CUSTOMER = 'customer';

    public const TYPE_ADMIN = 'admin';

    public function isAdmin(): bool
    {
        return $this->user_type === self::TYPE_ADMIN;
    }

    public function isCustomer(): bool
    {
        return $this->user_type === self::TYPE_CUSTOMER;
    }

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('user_type', self::TYPE_ADMIN);
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->where('user_type', self::TYPE_CUSTOMER);
    }

    /**
     * Spatie resolves permissions per guard. Staff roles live on the `admin`
     * guard and customer roles on `web`, so the same permission name can never
     * accidentally be satisfied by the wrong kind of account.
     */
    public function guardName(): string
    {
        return $this->isAdmin() ? 'admin' : 'web';
    }

    // ----------------------------------------------------------------- accounts

    public function disabledBy(): HasOne
    {
        return $this->hasOne(self::class, 'id', 'disabled_by');
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function assignedCases(): HasMany
    {
        return $this->hasMany(LegalCase::class, 'assigned_to');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(SessionDevice::class);
    }

    public function isDisabled(): bool
    {
        return ! $this->is_active || $this->disabled_at !== null;
    }

    public function isLockedOut(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function secondsUntilUnlock(): int
    {
        return $this->isLockedOut() ? now()->diffInSeconds($this->locked_until) : 0;
    }

    // ---------------------------------------------------------------------- 2FA

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Whether 2FA is mandatory for this account.
     * Admin 2FA is contractual, so the default when unconfigured is TRUE.
     */
    public function requiresTwoFactor(): bool
    {
        if (! $this->isAdmin()) {
            return false;
        }

        foreach ($this->roles as $role) {
            if (setting('security.enforce_2fa_'.str($role->name)->slug('_')->toString(), true)) {
                return true;
            }
        }

        return true;
    }
}
