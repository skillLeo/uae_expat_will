<?php

namespace Database\Seeders;

use App\Domain\Settings\Enums\SettingGroup as G;
use App\Domain\Settings\Enums\SettingType as T;
use App\Domain\Settings\Services\SettingsRepository;
use Illuminate\Database\Seeder;

/**
 * Every runtime setting, with a sane default.
 *
 * define() only writes the default on first creation, so re-running this seeder
 * never clobbers a value an administrator has set.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $s = app(SettingsRepository::class);

        // ------------------------------------------------------------ branding
        $s->define(G::Branding, 'branding.platform_name', 'UAE Expat Wills', T::String, 'Platform name', null, true, 1);
        $s->define(G::Branding, 'branding.short_line', 'A Summit Legal Consultancy UAE Platform', T::String, 'Short brand line', 'Appears under the wordmark. The phrase "Supported by Summit" must never appear anywhere.', true, 2);
        $s->define(G::Branding, 'branding.ownership_line', 'Owned and operated by Summit Legal Consultancy UAE · Trade Licence No. 4429232.01', T::String, 'Ownership line', 'Rendered on every page and in every email. Compliance requires this exact wording.', true, 3);
        $s->define(G::Branding, 'branding.trade_licence', '4429232.01', T::String, 'Trade licence number', null, true, 4);
        // one_name is live, chosen by Summit in August 2026. Switching the whole
        // product to another direction is this one value — the Wordmark component
        // reads it and every page, email and export follows with no rebuild.
        $s->define(G::Branding, 'branding.wordmark_direction', 'one_name', T::String, 'Wordmark direction', 'one_name (live) · margin (1b) · engrossment (1a) · registers (1c)', true, 5);
        $s->define(G::Branding, 'branding.logo_light', null, T::File, 'Logo — light ground', null, true, 6);
        $s->define(G::Branding, 'branding.logo_dark', null, T::File, 'Logo — ink ground', null, true, 7);
        $s->define(G::Branding, 'branding.favicon', null, T::File, 'Favicon', null, true, 8);
        $s->define(G::Branding, 'branding.og_image', null, T::File, 'Open Graph image', null, true, 9);

        // ------------------------------------------------------------- contact
        $s->define(G::Contact, 'contact.email', 'info@uaeexpatwills.com', T::String, 'Public email address', null, true, 1);
        $s->define(G::Contact, 'contact.whatsapp_number', '+971 52 466 6191', T::String, 'Public WhatsApp number', null, true, 2);
        $s->define(G::Contact, 'contact.working_hours', 'Sunday to Thursday, 09:00–18:00 Gulf Standard Time', T::String, 'Working hours', null, true, 3);
        $s->define(G::Contact, 'contact.out_of_hours_message', 'We are currently closed. Messages sent now are answered on the next working day.', T::Text, 'Out-of-hours message', null, true, 4);
        $s->define(G::Contact, 'contact.registered_entity', 'Summit Legal Consultancy FZC', T::String, 'Registered entity name', null, true, 5);
        // No address is displayed anywhere on the site — a compliance rule. This
        // stays empty until Summit explicitly asks for it.
        $s->define(G::Contact, 'contact.registered_address', null, T::Text, 'Registered address', 'Hidden until set. Compliance rule: no address is displayed anywhere on the site.', true, 6);

        // ---------------------------------------------------------- commercial
        // Set by Summit on 31 August 2026. Every page, FAQ, legal clause,
        // result screen and email reads this through the {fee} token, so the
        // price lives in exactly one place and a change is never a dozen edits.
        //
        // Keep this in step with the migration that sets it on a live database.
        // define() only writes a default when the row does not exist, so the
        // two are set separately and drifting apart would mean tests pass
        // against a price the site does not charge.
        $s->define(G::Commercial, 'commercial.standard_fee', 10000, T::Integer, 'Standard professional fee (AED)', 'Excluding VAT. One accepted standard Will.', true, 1);
        // Two coordinated but legally separate Wills. Not two times the single
        // fee — it is its own price, so it is its own setting.
        $s->define(G::Commercial, 'commercial.mirror_fee', 15000, T::Integer, 'Mirror Wills professional fee (AED)', 'Excluding VAT. Two coordinated but legally separate standard Wills.', true, 2);
        $s->define(G::Commercial, 'commercial.difc_starting_fee', 3999, T::Integer, 'DIFC starting fee (AED)', 'Never shown as a fixed purchasable price — always "from".', true, 3);
        $s->define(G::Commercial, 'commercial.vat_rate', 5, T::Integer, 'VAT rate (%)', null, true, 4);
        $s->define(G::Commercial, 'commercial.currency', 'AED', T::String, 'Currency', null, true, 5);
        $s->define(G::Commercial, 'commercial.authority_fees', [
            ['route' => 'ADJD Civil Will', 'amount' => 'AED 950.00', 'note' => "For one regular Will, subject to ADJD's current service, eligibility and fee schedule"],
            ['route' => 'Dubai Courts Will', 'amount' => '≈ AED 2,100.00', 'note' => 'For one Will, subject to the service and fee confirmed by Dubai Courts'],
            ['route' => 'DIFC Courts Will', 'amount' => 'Varies by Will type', 'note' => 'Confirmed from the current DIFC fee schedule with the individual quotation'],
        ], T::Json, 'Authority fee table', 'Open item 06 — re-check each figure against the authority schedule before launch.', true, 6);
        $s->define(G::Commercial, 'commercial.amendment_allowance', 2, T::Integer, 'Amendment rounds included', 'Open item 07 — the specification says "as stated in your Service Confirmation" without fixing a number.', true, 7);
        $s->define(G::Commercial, 'commercial.first_draft_days', 2, T::Integer, 'First-draft target (business days)', 'Open item 09 — confirm Summit stands behind this, it appears in client-facing copy.', true, 8);

        // -------------------------------------------------------------- mail
        $s->define(G::Mail, 'mail.driver', 'smtp', T::String, 'Mail driver', null, false, 1);
        $s->define(G::Mail, 'mail.host', '', T::String, 'SMTP host', null, false, 2);
        $s->define(G::Mail, 'mail.port', 587, T::Integer, 'SMTP port', null, false, 3);
        $s->define(G::Mail, 'mail.username', '', T::String, 'SMTP username', null, false, 4);
        $s->define(G::Mail, 'mail.password', null, T::Encrypted, 'SMTP password', 'Encrypted at rest. Never displayed after saving.', false, 5);
        $s->define(G::Mail, 'mail.encryption', 'tls', T::String, 'Encryption', null, false, 6);
        $s->define(G::Mail, 'mail.from_address', 'noreply@uaeexpatwills.com', T::String, 'From address', null, false, 7);
        $s->define(G::Mail, 'mail.from_name', 'UAE Expat Wills', T::String, 'From name', null, false, 8);
        $s->define(G::Mail, 'mail.reply_to', 'info@uaeexpatwills.com', T::String, 'Reply-to address', null, false, 9);

        // ----------------------------------------------------------- whatsapp
        $s->define(G::Whatsapp, 'whatsapp.phone_number_id', '', T::String, 'Meta phone number ID', null, false, 1);
        $s->define(G::Whatsapp, 'whatsapp.business_account_id', '', T::String, 'Business account ID', null, false, 2);
        $s->define(G::Whatsapp, 'whatsapp.access_token', null, T::Encrypted, 'Access token', 'Encrypted at rest.', false, 3);
        $s->define(G::Whatsapp, 'whatsapp.api_version', 'v21.0', T::String, 'Graph API version', null, false, 4);
        $s->define(G::Whatsapp, 'whatsapp.webhook_verify_token', null, T::Encrypted, 'Webhook verify token', null, false, 5);
        $s->define(G::Whatsapp, 'whatsapp.admin_number_1', '', T::String, 'Administrator alert number 1', 'Internal alerts go to both numbers.', false, 6);
        $s->define(G::Whatsapp, 'whatsapp.admin_number_2', '', T::String, 'Administrator alert number 2', null, false, 7);

        // ------------------------------------------------------------ payment
        $s->define(G::Payment, 'payment.gateway', 'telr', T::String, 'Gateway', 'telr · network_international · other', false, 1);
        $s->define(G::Payment, 'payment.store_id', '', T::String, 'Store ID', null, false, 2);
        $s->define(G::Payment, 'payment.auth_key', null, T::Encrypted, 'Authentication key', 'Encrypted at rest.', false, 3);
        $s->define(G::Payment, 'payment.test_mode', true, T::Boolean, 'Test mode', 'Leave on until Summit confirms live credentials.', false, 4);
        $s->define(G::Payment, 'payment.webhook_secret', null, T::Encrypted, 'Webhook signing secret', 'Used to verify inbound webhook signatures.', false, 5);
        $s->define(G::Payment, 'payment.return_url', '/payment/complete', T::String, 'Return URL', null, false, 6);
        $s->define(G::Payment, 'payment.cancel_url', '/payment/cancelled', T::String, 'Cancel URL', null, false, 7);

        // ---------------------------------------------------------- analytics
        // Tags must not render before cookie consent. These being set is not
        // sufficient for them to load — see the consent gate.
        $s->define(G::Analytics, 'analytics.ga4_measurement_id', '', T::String, 'GA4 measurement ID', 'Only loads after the visitor accepts analytics cookies.', true, 1);
        $s->define(G::Analytics, 'analytics.gtm_container_id', '', T::String, 'GTM container ID', 'Only loads after the visitor accepts analytics cookies.', true, 2);
        $s->define(G::Analytics, 'analytics.search_console_verification', '', T::String, 'Search Console verification', null, true, 3);

        // ----------------------------------------------------------- security
        $s->define(G::Security, 'security.session_lifetime_minutes', 120, T::Integer, 'Session lifetime (minutes)', null, false, 1);
        $s->define(G::Security, 'security.max_login_attempts', 5, T::Integer, 'Maximum login attempts', null, false, 2);
        $s->define(G::Security, 'security.lockout_minutes', 15, T::Integer, 'Lockout duration (minutes)', null, false, 3);
        foreach (['super_administrator', 'administrator', 'legal_reviewer', 'case_handler', 'finance', 'read_only'] as $i => $role) {
            $s->define(G::Security, 'security.enforce_2fa_'.$role, true, T::Boolean, 'Enforce 2FA — '.str_replace('_', ' ', ucfirst($role)), null, false, 10 + $i);
        }

        // ---------------------------------------------------------- retention
        $s->define(G::Retention, 'retention.incomplete_assessment_days', 30, T::Integer, 'Incomplete assessments (days)', 'The only period the specification actually fixes.', false, 1);
        $s->define(G::Retention, 'retention.abandoned_account_days', 180, T::Integer, 'Abandoned accounts (days)', 'Open item 08 — proposed, not specified.', false, 2);
        $s->define(G::Retention, 'retention.unsuccessful_enquiry_days', 365, T::Integer, 'Unsuccessful enquiries (days)', 'Open item 08 — proposed, not specified.', false, 3);
        $s->define(G::Retention, 'retention.completed_file_years', 7, T::Integer, 'Completed files (years)', 'Open item 08 — proposed, not specified.', false, 4);

        // ----------------------------------------------------------- features
        // client_portal_enabled is FALSE on purpose. The client area is fully
        // built but is commercially gated: nothing is reachable until Summit
        // approves that phase in writing.
        $s->define(G::Features, 'features.client_portal_enabled', false, T::Boolean, 'Client portal', 'OFF until Summit approves the client-area phase in writing. Open item 04.', true, 1);
        $s->define(G::Features, 'features.client_login_in_header', false, T::Boolean, 'Client login link in header', 'Open item 03. The utility slot is reserved at 96×24 so this drops in without relayout.', true, 2);
        $s->define(G::Features, 'features.document_upload_enabled', true, T::Boolean, 'Document upload', null, true, 3);
        $s->define(G::Features, 'features.whatsapp_enabled', false, T::Boolean, 'WhatsApp notifications', 'Open item 10 — the 11 templates need Meta Utility approval first.', true, 4);
        $s->define(G::Features, 'features.self_serve_checkout_enabled', true, T::Boolean, 'Self-serve checkout', null, true, 5);
    }
}
