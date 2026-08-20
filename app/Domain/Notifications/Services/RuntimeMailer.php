<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Settings\Services\SettingsRepository;
use App\Models\SystemHeartbeat;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * Rebuilds the mail configuration from the settings table at runtime.
 *
 * The contract requires mail credentials to be managed from the admin panel, not
 * from .env, so the transport is reconstructed from the database rather than
 * read from config at boot. apply() is called before any send.
 */
class RuntimeMailer
{
    public function __construct(private SettingsRepository $settings) {}

    public function apply(): void
    {
        $host = $this->settings->get('mail.host');

        // Nothing configured yet — leave the framework default (log driver in
        // local) alone rather than half-configuring a broken transport.
        if (blank($host)) {
            return;
        }

        Config::set('mail.default', $this->settings->get('mail.driver', 'smtp'));
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => $host,
            'port' => (int) $this->settings->get('mail.port', 587),
            'username' => $this->settings->get('mail.username'),
            'password' => $this->settings->get('mail.password'),
            'encryption' => $this->settings->get('mail.encryption', 'tls'),
            'timeout' => 15,
        ]);
        Config::set('mail.from', [
            'address' => $this->settings->get('mail.from_address', 'noreply@uaeexpatwills.com'),
            'name' => $this->settings->get('mail.from_name', 'UAE Expat Wills'),
        ]);

        Mail::purge('smtp');
    }

    /**
     * Send a test message, surfacing the ACTUAL SMTP error on failure.
     *
     * A test button that says "failed" without saying why is useless — the whole
     * point is to let an administrator fix their own configuration.
     *
     * @return array{ok: bool, message: string}
     */
    public function sendTest(string $recipient): array
    {
        try {
            $this->apply();

            Mail::raw(
                "This is a test message from the UAE Expat Wills platform.\n\n"
                ."If you received it, the mail settings are working.\n\n"
                .setting('branding.ownership_line'),
                fn ($message) => $message->to($recipient)->subject('Test message — UAE Expat Wills')
            );

            // Recorded so the health panel can report whether email actually
            // leaves the server. A wrong password is otherwise invisible until
            // a customer says they never received anything.
            SystemHeartbeat::beat('mail_test', 'ok', ['recipient' => $recipient]);

            return ['ok' => true, 'message' => "Test message sent to {$recipient}."];
        } catch (\Throwable $e) {
            SystemHeartbeat::beat('mail_test', 'failed', ['error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
