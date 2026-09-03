<?php

namespace Database\Seeders;

use App\Domain\Notifications\Enums\NotificationChannel;
use App\Models\NotificationTemplate;
use Illuminate\Database\Seeder;

/**
 * The eleven notification templates, email and WhatsApp.
 *
 * Every template is a database row and is editable from the admin template
 * editor. What no template may ever contain is enforced by review, not by code,
 * so it is restated here for whoever edits them next:
 *
 *   - The capacity or undue-influence flag, or any hint that one exists.
 *   - Any statement that a Will is registered, or that an authority will accept it.
 *   - A guaranteed registration date or processing period.
 *   - Instructions, beneficiary names or asset detail — those stay in the account.
 *   - A request for a password, card number or seed phrase.
 */
class NotificationTemplateSeeder extends Seeder
{
    /**
     * Correspondence that goes out from the licensed firm rather than the
     * platform, decided by Summit on 3 September 2026.
     *
     * A draft Will arriving from wills@summitlegaluae.com carries weight the
     * same document from a marketing domain does not. Everything
     * administrative — receipts, reminders, assessment results, internal
     * alerts — stays on the platform address.
     *
     * An administrator can move any template between the two identities; this
     * is only where each one starts.
     */
    private const FROM_THE_FIRM = [
        'draft_ready',
        'draft_approved',
        'further_information_required',
        'registration_appointment',
        'matter_completed',
    ];

    public function run(): void
    {
        $templates = json_decode(file_get_contents(__DIR__.'/data/notification_templates.json'), true);

        foreach ($templates as $template) {
            $this->email($template);
            $this->whatsapp($template);
        }
    }

    /** @param array<string, mixed> $t */
    private function email(array $t): void
    {
        $body = implode("\n\n", $t['emailBody'] ?? []);

        if (! empty($t['caveat'])) {
            $body .= "\n\n".$t['caveat'];
        }

        NotificationTemplate::updateOrCreate(
            ['key' => $t['slug'], 'channel' => NotificationChannel::Email, 'locale' => 'en'],
            [
                'subject' => $t['emailTitle'],
                'body' => $body,
                'variables' => $this->variables($t['variables'] ?? ''),
                'is_active' => true,
                // Null means "use the global sending address".
                'from_address' => in_array($t['slug'], self::FROM_THE_FIRM, true)
                    ? 'wills@summitlegaluae.com'
                    : null,
                'from_name' => in_array($t['slug'], self::FROM_THE_FIRM, true)
                    ? 'Summit Legal Consultancy UAE'
                    : null,
            ],
        );
    }

    /** @param array<string, mixed> $t */
    private function whatsapp(array $t): void
    {
        NotificationTemplate::updateOrCreate(
            ['key' => $t['slug'], 'channel' => NotificationChannel::Whatsapp, 'locale' => 'en'],
            [
                'subject' => null,
                'body' => implode("\n\n", $t['waBody'] ?? []),
                'whatsapp_header' => $t['waHeader'] ?? null,
                'whatsapp_footer' => $t['waFooter'] ?? null,
                'whatsapp_buttons' => $t['waButtons'] ?? [],
                'variables' => $this->variables($t['variables'] ?? ''),
                'meta_template_name' => $t['slug'],
                // Open item 10: all eleven need Meta Utility-category approval
                // before launch, and approval takes days rather than hours.
                'meta_status' => 'pending_submission',
                'is_active' => true,
            ],
        );
    }

    /** @return array<int, string> */
    private function variables(string $raw): array
    {
        preg_match_all('/\{\{(\d+)\}\}\s*([a-z ]+)/i', $raw, $matches, PREG_SET_ORDER);

        return array_map(fn ($m) => trim($m[2]), $matches);
    }
}
