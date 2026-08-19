<?php

namespace App\Domain\Notifications\Actions;

use App\Domain\Assessment\DTOs\RoutingResult;
use App\Domain\Notifications\Enums\NotificationChannel;
use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Models\LegalCase;

/**
 * Tells Summit a new assessment has landed.
 *
 * THE CONTENT OF THIS ALERT IS A COMPLIANCE BOUNDARY. It carries the reference,
 * the outcome bucket, the owner and the due time — and nothing else. For a
 * restricted case it says only that a matter requires immediate legal attention.
 * It names no question, no answer and no reason, because a WhatsApp message can
 * be read over someone's shoulder and the whole point of the restriction is that
 * the customer's answer must not reach the person who may be influencing them.
 */
class SendInternalNewLeadAlert
{
    public function __construct(private NotificationDispatcher $dispatcher) {}

    public function execute(LegalCase $case, RoutingResult $result): void
    {
        $data = [
            'reference' => $case->reference,
            'time' => now()->format('H:i'),
            // The BUCKET, never the internal status and never the reason.
            'outcome' => $result->isRestricted()
                ? 'Requires immediate legal attention'
                : $result->outcome->label(),
            'owner' => $case->assignee?->name ?? 'Unassigned',
            'due' => $case->countdown_due_at?->format('d M · H:i') ?? '—',
            // A count is safe: it says how many triggers fired, never what they were.
            'trigger_count' => $result->triggerCount(),
        ];

        foreach ($this->recipients() as $number) {
            $this->dispatcher->send(
                'internal_new_lead', NotificationChannel::Whatsapp, $number, $data, $case,
            );
        }

        if ($inbox = setting('contact.email')) {
            $this->dispatcher->send(
                'internal_new_lead', NotificationChannel::Email, $inbox, $data, $case,
            );
        }
    }

    /** @return array<int, string> */
    private function recipients(): array
    {
        return array_values(array_filter([
            setting('whatsapp.admin_number_1'),
            setting('whatsapp.admin_number_2'),
        ]));
    }
}
