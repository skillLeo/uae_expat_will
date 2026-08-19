<?php

namespace App\Domain\Settings\Enums;

enum SettingGroup: string
{
    case Branding = 'branding';
    case Contact = 'contact';
    case Commercial = 'commercial';
    case Mail = 'mail';
    case Whatsapp = 'whatsapp';
    case Payment = 'payment';
    case Analytics = 'analytics';
    case Security = 'security';
    case Retention = 'retention';
    case Features = 'features';

    public function label(): string
    {
        return match ($this) {
            self::Branding => 'Branding and identity',
            self::Contact => 'Contact details',
            self::Commercial => 'Fees and commercial terms',
            self::Mail => 'Email delivery',
            self::Whatsapp => 'WhatsApp',
            self::Payment => 'Payment gateway',
            self::Analytics => 'Analytics',
            self::Security => 'Security',
            self::Retention => 'Data retention',
            self::Features => 'Feature flags',
        };
    }

    /** Groups holding credentials require the settings.integrations permission. */
    public function requiresIntegrationsPermission(): bool
    {
        return in_array($this, [self::Mail, self::Whatsapp, self::Payment], true);
    }
}
