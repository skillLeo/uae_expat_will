<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Notifications\Services\NotificationDispatcher;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationTemplateController extends Controller
{
    /** Sample values used for the variable preview and the test send. */
    private const SAMPLE = [
        'reference' => 'SLC-2026-04418',
        'first_name' => 'Jordan',
        'time' => '09:05',
        'outcome' => 'Held for review',
        'owner' => 'Dr. M. Raouf',
        'due' => '19 Aug · 13:05',
        'trigger_count' => '3',
        'amount' => '2,308.95',
        'masked_email' => 'j****@example.com',
        'date' => '19 Aug 2026',
    ];

    public function index(): Response
    {
        return Inertia::render('Admin/Notifications/Index', [
            'templates' => NotificationTemplate::orderBy('key')->orderBy('channel')->get()
                ->groupBy('key')
                ->map(fn ($group, $key) => [
                    'key' => $key,
                    'channels' => $group->map(fn (NotificationTemplate $t) => [
                        'id' => $t->id,
                        'channel' => $t->channel->value,
                        'subject' => $t->subject,
                        'is_active' => $t->is_active,
                        'meta_status' => $t->meta_status,
                    ])->values(),
                ])->values(),
            'recent' => NotificationLog::with('legalCase:id,reference')
                ->latest()->limit(25)->get()
                ->map(fn (NotificationLog $l) => [
                    'template_key' => $l->template_key,
                    'channel' => $l->channel->value,
                    'recipient' => $l->recipient,
                    'status' => $l->status->value,
                    'tone' => $l->status->tone(),
                    'error' => $l->error,
                    'is_fallback' => $l->fallback_of_id !== null,
                    'at' => $l->created_at->toIso8601String(),
                ]),
        ]);
    }

    public function edit(NotificationTemplate $template): Response
    {
        return Inertia::render('Admin/Notifications/Edit', [
            'template' => [
                'id' => $template->id,
                'key' => $template->key,
                'channel' => $template->channel->value,
                'subject' => $template->subject,
                'body' => $template->body,
                'whatsapp_header' => $template->whatsapp_header,
                'whatsapp_footer' => $template->whatsapp_footer,
                'whatsapp_buttons' => $template->whatsapp_buttons,
                'variables' => $template->variables,
                'meta_template_name' => $template->meta_template_name,
                'meta_status' => $template->meta_status,
                'is_active' => $template->is_active,
            ],
            'sample' => self::SAMPLE,
            'preview' => [
                'subject' => $template->render('subject', self::SAMPLE),
                'body' => $template->render('body', self::SAMPLE),
            ],
            // Restated on the edit screen because this is where someone is most
            // likely to add something that must never be in a message.
            'forbidden' => [
                'The capacity or undue-influence flag, or any hint that one exists.',
                'Any statement that a Will is registered, or that an authority will accept it.',
                'A guaranteed registration date or processing period.',
                'Instructions, beneficiary names or asset detail.',
                'A request for a password, card number or seed phrase.',
            ],
        ]);
    }

    public function update(Request $request, NotificationTemplate $template): RedirectResponse
    {
        $template->update($request->validate([
            'subject' => 'nullable|string|max:500',
            'body' => 'required|string|max:20000',
            'whatsapp_header' => 'nullable|string|max:200',
            'whatsapp_footer' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ]));

        activity('notifications')
            ->performedOn($template)
            ->causedBy($request->user('admin'))
            ->withProperties(['key' => $template->key, 'channel' => $template->channel->value])
            ->log('Notification template edited');

        return back()->with('success', 'Template saved.');
    }

    public function test(Request $request, NotificationTemplate $template, NotificationDispatcher $dispatcher): RedirectResponse
    {
        $validated = $request->validate(['recipient' => 'required|string|max:190']);

        $log = $dispatcher->send(
            $template->key,
            $template->channel,
            $validated['recipient'],
            self::SAMPLE,
            allowFallback: false,
        );

        return back()->with(
            $log->status->value === 'sent' ? 'success' : 'error',
            $log->status->value === 'sent'
                ? "Test sent to {$validated['recipient']}."
                : 'Send failed: '.($log->error ?? 'unknown error'),
        );
    }
}
