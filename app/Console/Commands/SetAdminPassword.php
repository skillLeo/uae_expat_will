<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Sets a staff password from the console.
 *
 * An administrator locked out of this platform has no way back in on their
 * own. There is deliberately no forgot-password route on the admin side —
 * staff accounts are provisioned, not self-registered — and even if there
 * were, the platform has no mail server configured, so the message could not
 * be delivered. Someone with server access has to do it, and until now that
 * meant hand-writing a tinker one-liner, which is how the client sat unable to
 * log in for days.
 *
 * The password is prompted for, never passed as an argument. An argument would
 * be visible in shell history and to anyone running `ps` on a shared host, and
 * it would end up pasted into a chat window on its way to being typed. This
 * way the only place it exists is the operator's keyboard and the hash in the
 * database.
 *
 * Enrolment is a separate question from the password. Someone who has lost
 * their authenticator needs the second factor cleared as well, and someone who
 * has merely forgotten a password must NOT have it cleared — so it is a flag,
 * it is confirmed out loud, and it defaults to leaving 2FA alone.
 */
class SetAdminPassword extends Command
{
    protected $signature = 'admin:password
                            {email : The staff email address}
                            {--reset-2fa : Also clear two-factor enrolment, so they set it up again on next login}';

    protected $description = 'Set a staff password from the console, prompting for it rather than taking it as an argument';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No account with the email {$email}.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail('Account', $user->name);
        $this->components->twoColumnDetail('Email', $user->email);
        $this->components->twoColumnDetail('Roles', $user->roles->pluck('name')->implode(', ') ?: 'none');
        $this->components->twoColumnDetail('Active', $user->is_active ? 'yes' : 'NO — they still will not get in');
        $this->components->twoColumnDetail(
            'Two-factor',
            $user->hasTwoFactorEnabled() ? 'set up' : 'not set up — they enrol on first login'
        );
        $this->components->twoColumnDetail('Last login', (string) ($user->last_login_at ?? 'never'));
        $this->newLine();

        if (! $this->confirm('Set a new password for this account?', true)) {
            $this->components->info('Nothing changed.');

            return self::SUCCESS;
        }

        $password = $this->secret('New password');
        $again = $this->secret('Type it again');

        if ($password !== $again) {
            $this->components->error('They do not match. Nothing changed.');

            return self::FAILURE;
        }

        // The same rules the platform enforces elsewhere. A console shortcut
        // that quietly accepts a weaker password than the login form would is
        // a hole in the policy, not a convenience.
        $check = Validator::make(['password' => $password], [
            'password' => ['required', 'string', 'min:12'],
        ], [
            'password.min' => 'The password must be at least 12 characters.',
        ]);

        if ($check->fails()) {
            foreach ($check->errors()->all() as $message) {
                $this->components->error($message);
            }

            return self::FAILURE;
        }

        // Cast to 'hashed' on the model, so this is never stored in the clear.
        $user->password = $password;

        if ($this->option('reset-2fa')) {
            if (! $this->confirm('Also clear two-factor, so they enrol again on next login?', false)) {
                $this->components->info('Two-factor left as it is.');
            } else {
                $user->two_factor_secret = null;
                $user->two_factor_recovery_codes = null;
                $user->two_factor_confirmed_at = null;
                $this->components->warn('Two-factor cleared. They will be asked to set it up again.');
            }
        }

        $user->save();

        $this->newLine();
        $this->components->info('Password set. It has not been printed anywhere — give it to them directly.');

        if (! $user->hasTwoFactorEnabled()) {
            $this->components->info('They will be walked through two-factor setup the first time they log in.');
        }

        return self::SUCCESS;
    }
}
