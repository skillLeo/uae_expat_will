<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two sending identities, decided by Summit on 3 September 2026.
 *
 * Correspondence carrying or concerning the Will itself goes out from
 * wills@summitlegaluae.com, the licensed firm's own domain, because a legal
 * document arriving from the regulated entity carries weight that the same
 * document from a marketing domain does not. Everything administrative —
 * receipts, reminders, assessment results, internal alerts — stays on
 * no-reply@uaeexpatwills.com.
 *
 * Held per template rather than as two global settings, because which
 * correspondence counts as legal is Summit's judgement and will change. A
 * null address means "use the global one", so nothing has to be filled in for
 * the ordinary case.
 *
 * NOTE: the sending account must be permitted to send as this address.
 * Microsoft 365 rejects a From it has not granted Send As for, and the
 * receiving side checks SPF and DMARC on summitlegaluae.com separately. Both
 * are mail-administration tasks, not code.
 */
return new class extends Migration
{
    /** Correspondence about the Will document itself. */
    private const LEGAL = [
        'draft_ready',
        'draft_approved',
        'further_information_required',
        'registration_appointment',
        'matter_completed',
    ];

    public function up(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->string('from_address', 190)->nullable()->after('body');
            $table->string('from_name', 120)->nullable()->after('from_address');
        });

        DB::table('notification_templates')
            ->whereIn('key', self::LEGAL)
            ->update([
                'from_address' => 'wills@summitlegaluae.com',
                'from_name' => 'Summit Legal Consultancy UAE',
                'updated_at' => now(),
            ]);

        // The address Summit actually created carries a hyphen. Sending from
        // a mailbox that does not exist means bounces, or a silent rejection
        // by the provider — and nobody finds out until a customer says they
        // never got their receipt.
        DB::table('settings')
            ->where('key', 'mail.from_address')
            ->where('value', 'noreply@uaeexpatwills.com')
            ->update(['value' => 'no-reply@uaeexpatwills.com', 'updated_at' => now()]);

        Cache::flush();
    }

    public function down(): void
    {
        Schema::table('notification_templates', function (Blueprint $table) {
            $table->dropColumn(['from_address', 'from_name']);
        });

        DB::table('settings')
            ->where('key', 'mail.from_address')
            ->update(['value' => 'noreply@uaeexpatwills.com', 'updated_at' => now()]);

        Cache::flush();
    }
};
