<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Says what the two-factor switch actually does, now that it does anything.
 *
 * The per-role switches existed, saved, and changed nothing: requiresTwoFactor()
 * ended in a bare `return true`. Anyone turning one off saw it save and saw no
 * difference, which is worse than not offering the switch at all.
 *
 * Now that it works, the help has to say plainly what switching it off costs,
 * because the person reading it is deciding whether to remove the only thing
 * standing between a guessed password and a client's Will.
 */
return new class extends Migration
{
    private const ROLES = [
        'super_administrator' => 'Super Administrator',
        'administrator' => 'Administrator',
        'legal_reviewer' => 'Legal Reviewer',
        'case_handler' => 'Case Handler',
        'finance' => 'Finance',
        'read_only' => 'Read Only',
    ];

    public function up(): void
    {
        foreach (self::ROLES as $slug => $label) {
            DB::table('settings')->where('key', 'security.enforce_2fa_'.$slug)->update([
                'help_text' => "ON: a {$label} signs in with email, password and a 6-digit code from their phone. "
                    .'OFF: email and password only, with no code and no app to set up. '
                    .'Switching it off is reasonable while setting the platform up. It must be back on before real client matters are held here, '
                    .'and the dashboard reports it as critical until it is. An existing code is kept, so switching back on needs nothing set up again.',
                'updated_at' => now(),
            ]);
        }

        Cache::flush();
    }

    public function down(): void
    {
        // The previous help was empty; there is nothing to restore.
    }
};
