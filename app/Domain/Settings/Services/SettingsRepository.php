<?php

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Enums\SettingGroup;
use App\Domain\Settings\Enums\SettingType;
use App\Models\Setting;
use App\Models\SettingHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The single read/write path for runtime settings.
 *
 * Reads are cached for the request and in the application cache, because these
 * are read on essentially every page render. Writes always bust the cache, write
 * a history row and leave an audit entry — there is no path that updates a
 * setting without recording who did it.
 */
class SettingsRepository
{
    private const CACHE_KEY = 'settings.all';

    /** @var array<string, mixed>|null */
    private ?array $memo = null;

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        return $this->memo = Cache::rememberForever(self::CACHE_KEY, function () {
            return Setting::all()
                ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
                ->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        return $value === null ? $default : $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    /** Only public settings may ever be shipped to the browser. */
    public function public(): array
    {
        return Setting::where('is_public', true)
            ->get()
            ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
            ->all();
    }

    /** @return Collection<int, Setting> */
    public function group(SettingGroup $group): Collection
    {
        return Setting::where('group', $group)->orderBy('order')->orderBy('key')->get();
    }

    /**
     * Write a setting, recording the change.
     *
     * A secret's old and new values are recorded REDACTED in history — the
     * history table would otherwise become a plaintext archive of every
     * credential the platform has ever held.
     */
    public function set(string $key, mixed $value): Setting
    {
        return DB::transaction(function () use ($key, $value) {
            $setting = Setting::where('key', $key)->firstOrFail();

            $old = $setting->type->isSecret() ? '••••••••' : $setting->attributes['value'] ?? null;
            $setting->setTypedValue($value);
            $new = $setting->type->isSecret() ? '••••••••' : $setting->attributes['value'] ?? null;

            if ($setting->isDirty('value')) {
                $setting->save();

                SettingHistory::create([
                    'setting_id' => $setting->id,
                    'old_value' => $old,
                    'new_value' => $new,
                    'changed_by' => Auth::id(),
                    'changed_at' => now(),
                ]);

                activity('settings')
                    ->performedOn($setting)
                    ->causedBy(Auth::user())
                    ->withProperties(['key' => $key, 'group' => $setting->group->value])
                    ->log('Setting changed');
            }

            $this->flush();

            return $setting;
        });
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach ($values as $key => $value) {
                $this->set($key, $value);
            }
        });
    }

    public function define(
        SettingGroup $group,
        string $key,
        mixed $default,
        SettingType $type,
        string $label,
        ?string $help = null,
        bool $isPublic = false,
        int $order = 0,
    ): Setting {
        $setting = Setting::firstOrNew(['key' => $key]);

        $setting->fill([
            'group' => $group,
            'type' => $type,
            'label' => $label,
            'help_text' => $help,
            'is_public' => $isPublic,
            'order' => $order,
        ]);

        // Only seed the default on first creation — never clobber a real value.
        if (! $setting->exists) {
            $setting->setTypedValue($default);
        }

        $setting->save();
        $this->flush();

        return $setting;
    }

    public function flush(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    // ------------------------------------------------------------ feature flags

    /**
     * Feature flags default to FALSE when absent.
     *
     * client_portal_enabled in particular is commercially gated: the client area
     * is fully built but must not be reachable until Summit approves that phase
     * in writing. An absent or unreadable flag must therefore never read as "on".
     */
    public function feature(string $flag): bool
    {
        return (bool) $this->get('features.'.$flag, false);
    }
}
