<?php

use App\Domain\Settings\Services\SettingsRepository;
use App\Models\NotificationTemplate;
use Database\Seeders\NotificationTemplateSeeder;
use Illuminate\Support\Facades\Mail;

/**
 * Which address each kind of email goes out from.
 *
 * Summit uses two identities, decided 3 September 2026. Correspondence about
 * the Will itself comes from the licensed firm, wills@summitlegaluae.com,
 * because a legal document arriving from the regulated entity carries weight
 * the same document from a marketing domain does not. Receipts, reminders,
 * assessment results and internal alerts stay on the platform address.
 *
 * The platform address is no-reply@uaeexpatwills.com, with the hyphen. That is
 * the mailbox that actually exists; sending from one that does not means
 * bounces or a silent rejection, and nobody finds out until a customer says
 * their receipt never arrived.
 */
beforeEach(function () {
    seedPlatform();
    test()->seed(NotificationTemplateSeeder::class);
    Mail::fake();
});

it('sends the draft, and everything about it, from the licensed firm', function () {
    foreach (['draft_ready', 'draft_approved', 'further_information_required',
        'registration_appointment', 'matter_completed'] as $key) {
        $template = NotificationTemplate::where('key', $key)->where('channel', 'email')->first();

        expect($template)->not->toBeNull("Template {$key} is missing.");
        expect($template->from_address)->toBe('wills@summitlegaluae.com', "{$key} should send from the firm.");
        expect($template->from_name)->toBe('Summit Legal Consultancy UAE');
    }
});

it('leaves administrative mail on the platform address', function () {
    foreach (['payment_receipt_vat', 'assessment_result_continue', 'assessment_result_held',
        'questionnaire_released', 'internal_new_lead'] as $key) {
        $template = NotificationTemplate::where('key', $key)->where('channel', 'email')->first();

        if ($template === null) {
            continue;
        }

        // Null means "use the global address", which is the platform one.
        expect($template->from_address)->toBeNull("{$key} should not override the sender.");
    }
});

it('uses the address Summit actually created, with the hyphen', function () {
    expect(setting('mail.from_address'))->toBe('no-reply@uaeexpatwills.com');
});

it('keeps replies going to the monitored inbox', function () {
    // Whichever identity sends, a person replying must reach a real inbox
    // rather than an unattended no-reply mailbox.
    expect(setting('mail.reply_to'))->toBe('info@uaeexpatwills.com')
        ->and(setting('contact.email'))->toBe('info@uaeexpatwills.com');
});

it('lets an administrator move a template between identities', function () {
    // Which correspondence counts as legal is Summit's judgement and will
    // change, so it has to be editable rather than compiled in.
    $template = NotificationTemplate::where('key', 'payment_receipt_vat')->where('channel', 'email')->first();

    $template->update([
        'from_address' => 'wills@summitlegaluae.com',
        'from_name' => 'Summit Legal Consultancy UAE',
    ]);

    expect($template->fresh()->from_address)->toBe('wills@summitlegaluae.com');

    $template->update(['from_address' => null, 'from_name' => null]);

    expect($template->fresh()->from_address)->toBeNull();
});

it('applies the sender when the platform is asked to send', function () {
    app(SettingsRepository::class)->set('mail.host', 'smtp.office365.com');
    app(SettingsRepository::class)->set('mail.username', 'no-reply@uaeexpatwills.com');

    $template = NotificationTemplate::where('key', 'draft_ready')->where('channel', 'email')->first();

    // The dispatcher reads the override off the template rather than the
    // global setting, which is the whole mechanism.
    expect($template->from_address)->toBe('wills@summitlegaluae.com')
        ->and(setting('mail.from_address'))->toBe('no-reply@uaeexpatwills.com')
        ->and($template->from_address)->not->toBe(setting('mail.from_address'));
});
