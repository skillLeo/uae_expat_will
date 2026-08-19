<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * The two named Summit administrators.
 *
 * Passwords are random on every seed and printed once to the console. Nothing
 * memorable, nothing shared, and nothing committed — the first thing each
 * administrator does is enrol in 2FA, which is mandatory.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['Ahmed Mohammedi', 'ahmed@summitlegaluae.com', 'Super Administrator'],
            ['Dr. Mohamed Raouf', 'raouf@summitlegaluae.com', 'Legal Reviewer'],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $existing = User::where('email', $email)->first();

            if ($existing !== null) {
                $existing->syncRoles([$role]);

                continue;
            }

            $password = Str::password(20);

            $user = User::create([
                'user_type' => User::TYPE_ADMIN,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
                'timezone' => 'Asia/Dubai',
                'locale' => 'en',
            ]);

            $user->syncRoles([$role]);

            $this->command?->warn("  {$email}  {$password}");
        }

        $this->command?->warn('  Printed once. 2FA enrolment is required at first sign-in.');
    }
}
