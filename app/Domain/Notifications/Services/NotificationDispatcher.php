<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Contracts\WhatsAppClient;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Enums\NotificationStatus;
use App\Models\LegalCase;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use Illuminate\Support\Facades\Mail;

/**
 * Sends a database-driven notification and logs the result.
 *
 * Two rules matter here and both are contractual:
 *
 *   1. Every send is LOGGED with its delivery status.
 *   2. When an operational WhatsApp message fails, an email is sent
 *      automatically in its place, linked to the failed attempt.
 */
class NotificationDispatcher
{
    public function __construct(
        private RuntimeMailer $mailer,
        private WhatsAppClient $whatsapp,
    ) {}

    /**
     * @param  array<string, string|int|float|null>  $data
     */
    public function send(
        string $templateKey,
        NotificationChannel $channel,
        string $recipient,
        array $data = [],
        ?LegalCase $case = null,
        bool $allowFallback = true,
    ): NotificationLog {
        $template = NotificationTemplate::find($templateKey, $channel);

        if ($template === null) {
            return $this->log($templateKey, $channel, $recipient, $case, NotificationStatus::Failed, [
                'error' => "No active {$channel->value} template for '{$templateKey}'.",
            ]);
        }

        $log = $this->log($templateKey, $channel, $recipient, $case, NotificationStatus::Queued, [
            'payload' => $data,
        ]);

        $result = $channel === NotificationChannel::Whatsapp
            ? $this->sendWhatsApp($template, $recipient, $data)
            : $this->sendEmail($template, $recipient, $data);

        if ($result['ok']) {
            $log->update([
                'status' => NotificationStatus::Sent,
                'provider_reference' => $result['reference'] ?? null,
                'sent_at' => now(),
            ]);

            return $log;
        }

        $log->update([
            'status' => NotificationStatus::Failed,
            'error' => $result['error'],
            'failed_at' => now(),
        ]);

        // Automatic email fallback for a failed WhatsApp message.
        if ($allowFallback && ($fallbackChannel = $channel->fallback()) !== null) {
            $fallback = $this->send(
                $templateKey, $fallbackChannel, $recipient, $data, $case, allowFallback: false,
            );

            $fallback->update(['fallback_of_id' => $log->id]);
        }

        return $log;
    }

    /** @param array<string, string|int|float|null> $data */
    private function sendEmail(NotificationTemplate $template, string $recipient, array $data): array
    {
        try {
            $this->mailer->apply();

            $subject = $template->render('subject', $data);
            $body = $template->render('body', $data);

            // Every email carries the Summit identity and the trade licence, and
            // states plainly that the platform is not an authority.
            $footer = "\n\n---\n"
                .setting('branding.ownership_line')."\n"
                .'UAE Expat Wills and Summit Legal Consultancy UAE are not a court, registry, notary or government authority.';

            Mail::raw($body.$footer, function ($message) use ($recipient, $subject) {
                $message->to($recipient)->subject($subject);

                if ($replyTo = setting('mail.reply_to')) {
                    $message->replyTo($replyTo);
                }
            });

            return ['ok' => true, 'reference' => null, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'reference' => null, 'error' => $e->getMessage()];
        }
    }

    /** @param array<string, string|int|float|null> $data */
    private function sendWhatsApp(NotificationTemplate $template, string $recipient, array $data): array
    {
        if (! feature('whatsapp_enabled')) {
            return ['ok' => false, 'reference' => null, 'error' => 'WhatsApp is disabled.'];
        }

        return $this->whatsapp->sendTemplate(
            $recipient,
            $template->meta_template_name ?? $template->key,
            $data,
        );
    }

    /** @param array<string, mixed> $extra */
    private function log(
        string $key,
        NotificationChannel $channel,
        string $recipient,
        ?LegalCase $case,
        NotificationStatus $status,
        array $extra = [],
    ): NotificationLog {
        return NotificationLog::create([
            'case_id' => $case?->id,
            'template_key' => $key,
            'channel' => $channel,
            'recipient' => $recipient,
            'status' => $status,
            'payload' => $extra['payload'] ?? null,
            'error' => $extra['error'] ?? null,
            'failed_at' => $status === NotificationStatus::Failed ? now() : null,
        ]);
    }
}
