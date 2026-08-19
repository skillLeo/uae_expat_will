<?php

namespace App\Models;

use App\Domain\Settings\Enums\SettingGroup;
use App\Domain\Settings\Enums\SettingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

/**
 * A runtime setting.
 *
 * The mailer, the payment gateway and the WhatsApp client all rebuild their
 * configuration from these rows, so an administrator changes a credential
 * without a deploy and without touching .env.
 */
class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'label', 'help_text', 'is_public', 'order'];

    protected $hidden = ['value'];

    protected function casts(): array
    {
        return [
            'group' => SettingGroup::class,
            'type' => SettingType::class,
            'is_public' => 'boolean',
        ];
    }

    public function history(): HasMany
    {
        return $this->hasMany(SettingHistory::class)->latest('changed_at');
    }

    /** The decoded, decrypted value. */
    public function typedValue(): mixed
    {
        $raw = $this->attributes['value'] ?? null;

        if ($raw === null) {
            return null;
        }

        if ($this->type === SettingType::Encrypted) {
            try {
                $raw = Crypt::decryptString($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        return $this->type->cast($raw);
    }

    public function setTypedValue(mixed $value): void
    {
        $serialised = $this->type->serialise($value);

        $this->attributes['value'] = $serialised !== null && $this->type === SettingType::Encrypted
            ? Crypt::encryptString($serialised)
            : $serialised;
    }

    /** What history and the UI show for a secret: never the value itself. */
    public function displayValue(): mixed
    {
        if ($this->type->isSecret()) {
            return $this->attributes['value'] ? '••••••••' : null;
        }

        return $this->typedValue();
    }
}
