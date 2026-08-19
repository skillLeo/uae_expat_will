<?php

namespace App\Domain\Notifications\Contracts;

interface WhatsAppClient
{
    /**
     * @param  array<string, string|int|float|null>  $variables
     * @return array{ok: bool, reference: string|null, error: string|null}
     */
    public function sendTemplate(string $to, string $templateName, array $variables, string $locale = 'en'): array;
}
