<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Notifications\Contracts\WhatsAppClient;
use App\Domain\Notifications\Services\RuntimeMailer;
use App\Domain\Payments\Contracts\PaymentGateway;
use App\Domain\Settings\Enums\SettingGroup;
use App\Domain\Settings\RowSchemas;
use App\Domain\Settings\Services\SettingsRepository;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\SettingHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function __construct(private SettingsRepository $settings) {}

    public function index(Request $request, ?string $group = null): Response
    {
        $group = SettingGroup::tryFrom($group ?? 'branding') ?? SettingGroup::Branding;

        // The credential groups need a permission beyond settings.edit.
        abort_if(
            $group->requiresIntegrationsPermission() && ! $request->user('admin')->can('settings.integrations'),
            403,
        );

        return Inertia::render('Admin/Settings/Index', [
            'group' => ['value' => $group->value, 'label' => $group->label()],
            'groups' => collect(SettingGroup::cases())
                ->filter(fn (SettingGroup $g) => ! $g->requiresIntegrationsPermission()
                    || $request->user('admin')->can('settings.integrations'))
                ->map(fn (SettingGroup $g) => ['value' => $g->value, 'label' => $g->label()])
                ->values(),
            'settings' => $this->settings->group($group)->map(fn (Setting $s) => [
                'id' => $s->id,
                'key' => $s->key,
                'label' => $s->label,
                'help_text' => $s->help_text,
                'type' => $s->type->value,
                'is_secret' => $s->type->isSecret(),
                // A secret is never sent to the browser, only whether one is set.
                'value' => $s->type->isSecret() ? null : $s->typedValue(),
                'has_value' => $s->type->isSecret() ? filled($s->getAttributes()['value'] ?? null) : null,
                // A table-shaped setting gets labelled fields instead of raw
                // JSON, so the people whose prices these are can change them.
                'row_schema' => RowSchemas::for($s->key),
                'blank_row' => RowSchemas::for($s->key) ? RowSchemas::blankRow($s->key) : null,
            ])->values(),
            'history' => SettingHistory::with('changedBy:id,name', 'setting:id,key,label')
                ->whereHas('setting', fn ($q) => $q->where('group', $group))
                ->latest('changed_at')
                ->limit(20)
                ->get()
                ->map(fn (SettingHistory $h) => [
                    'key' => $h->setting?->key,
                    'label' => $h->setting?->label,
                    'old_value' => $h->old_value,
                    'new_value' => $h->new_value,
                    'by' => $h->changedBy?->name,
                    'at' => $h->changed_at->toIso8601String(),
                ]),
        ]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $settingGroup = SettingGroup::from($group);

        abort_if(
            $settingGroup->requiresIntegrationsPermission() && ! $request->user('admin')->can('settings.integrations'),
            403,
        );

        $allowed = $this->settings->group($settingGroup)->pluck('key')->all();
        $payload = collect($request->input('settings', []))
            ->only($allowed)
            // A blank secret means "leave it alone", not "erase it" — otherwise
            // saving any other field on the page would wipe the credentials.
            ->reject(fn ($value, $key) => $value === null
                && Setting::where('key', $key)->value('type') === 'encrypted')
            ->all();

        $this->settings->setMany($payload);

        return back()->with('success', $settingGroup->label().' saved.');
    }

    // ------------------------------------------------------------ test buttons

    /**
     * Each of these surfaces the ACTUAL provider error on failure. A test button
     * that says "failed" without saying why is useless — the whole point is to
     * let an administrator fix their own configuration.
     */
    public function testMail(Request $request, RuntimeMailer $mailer): RedirectResponse
    {
        $validated = $request->validate(['email' => 'required|email']);
        $result = $mailer->sendTest($validated['email']);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function testWhatsApp(Request $request, WhatsAppClient $client): RedirectResponse
    {
        $validated = $request->validate(['number' => 'required|string|max:32']);

        $result = $client->sendTemplate($validated['number'], 'internal_new_lead', [
            'reference' => 'TEST-0000',
            'time' => now()->format('H:i'),
            'outcome' => 'Test message',
            'owner' => $request->user('admin')->name,
            'due' => now()->addHours(4)->format('d M · H:i'),
        ]);

        return back()->with(
            $result['ok'] ? 'success' : 'error',
            $result['ok']
                ? "Test message sent to {$validated['number']}."
                : 'WhatsApp rejected the message: '.$result['error'],
        );
    }

    public function testGateway(PaymentGateway $gateway): RedirectResponse
    {
        // A zero-amount probe against a throwaway payment, never persisted.
        $probe = new Payment([
            'gateway' => $gateway->name(),
            'gateway_reference' => 'TEST-CONNECTION',
            'amount' => 0,
            'total_amount' => 0,
            'currency' => setting('commercial.currency', 'AED'),
        ]);

        $result = $gateway->checkStatus($probe);

        if ($result['ok']) {
            return back()->with('success', 'The gateway responded. Credentials are accepted.');
        }

        return back()->with('error', 'Gateway did not accept the credentials: '.($result['error'] ?? 'no response'));
    }
}
