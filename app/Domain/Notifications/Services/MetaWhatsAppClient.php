<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Contracts\WhatsAppClient;
use App\Domain\Settings\Services\SettingsRepository;
use Illuminate\Support\Facades\Http;

class MetaWhatsAppClient implements WhatsAppClient
{
    public function __construct(private SettingsRepository $settings) {}

    public function sendTemplate(string $to, string $templateName, array $variables, string $locale = 'en'): array
    {
        $phoneNumberId = $this->settings->get('whatsapp.phone_number_id');
        $token = $this->settings->get('whatsapp.access_token');

        if (blank($phoneNumberId) || blank($token)) {
            return ['ok' => false, 'reference' => null, 'error' => 'WhatsApp is not configured.'];
        }

        $version = $this->settings->get('whatsapp.api_version', 'v21.0');

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post("https://graph.facebook.com/{$version}/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->normalise($to),
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => $locale],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => array_values(array_map(
                                fn ($v) => ['type' => 'text', 'text' => (string) $v],
                                $variables,
                            )),
                        ]],
                    ],
                ]);

            if ($response->failed()) {
                return [
                    'ok' => false,
                    'reference' => null,
                    'error' => $response->json('error.message') ?? 'HTTP '.$response->status(),
                ];
            }

            return [
                'ok' => true,
                'reference' => $response->json('messages.0.id'),
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'reference' => null, 'error' => $e->getMessage()];
        }
    }

    private function normalise(string $number): string
    {
        return ltrim(preg_replace('/[^0-9]/', '', $number) ?? '', '0');
    }
}
