<?php

namespace App\Models;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Settings\Services\CommercialTokens;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class NotificationTemplate extends Model
{
    use RecordsActivity, SoftDeletes;

    protected $fillable = [
        'key', 'channel', 'subject', 'body', 'whatsapp_header', 'whatsapp_footer',
        'whatsapp_buttons', 'variables', 'meta_template_name', 'meta_status', 'is_active', 'locale',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'whatsapp_buttons' => 'array',
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public static function find(string $key, NotificationChannel $channel, string $locale = 'en'): ?self
    {
        return static::query()
            ->where('key', $key)
            ->where('channel', $channel)
            ->where('locale', $locale)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Render with {{ variable }} substitution.
     *
     * Unknown placeholders are left intact rather than blanked, so a broken
     * template is visibly broken in a test send instead of silently sending an
     * email with a hole in the middle of a sentence.
     *
     * @param  array<string, string|int|float|null>  $data
     */
    public function render(string $field, array $data): string
    {
        // The commercial placeholders resolve first, from settings, so an email
        // can never quote a price the website has stopped charging.
        $text = app(CommercialTokens::class)->apply((string) ($this->{$field} ?? ''));

        foreach ($data as $key => $value) {
            $text = str_replace(['{{ '.$key.' }}', '{{'.$key.'}}'], (string) $value, $text);
        }

        return $text;
    }
}
