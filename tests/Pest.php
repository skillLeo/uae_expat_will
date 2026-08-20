<?php

use App\Models\Questionnaire;
use App\Models\QuestionnaireVersion;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\QuestionnaireSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * Seeds the minimum a case-related test needs: permissions, roles and settings.
 */
function seedPlatform(): void
{
    test()->seed(PermissionSeeder::class);
    test()->seed(SettingsSeeder::class);
}

/** Pages, sections and FAQs — the public site's content. */
function seedContent(): void
{
    test()->seed(ContentSeeder::class);
}

function seedQuestionnaire(): QuestionnaireVersion
{
    test()->seed(QuestionnaireSeeder::class);

    return Questionnaire::screening()->publishedVersion();
}

/**
 * An admin with exactly the roles named, on the admin guard.
 *
 * @param  array<int, string>  $roles
 */
function adminUser(array $roles = ['Super Administrator']): User
{
    $user = User::factory()->create([
        'user_type' => User::TYPE_ADMIN,
        'two_factor_secret' => 'seeded',
        'two_factor_confirmed_at' => now(),
    ]);

    $user->syncRoles($roles);

    return $user->fresh();
}

/** A clean answer set that on its own routes to continue. */
function cleanAnswers(array $overrides = []): array
{
    return array_merge([
        'q1' => 'new_will',
        'q2' => 'yes',
        'q3' => 'GB',
        'q4' => 'outside_uae',
        'q5' => 'non_muslim',
        'q6' => 'married_first',
        'q7' => ['none'],
        'q9' => 'uae_only',
        'q10' => ['bank', 'real_estate'],
        'q11' => ['sole_owned'],
        'q12' => ['none'],
        'q13a' => ['to_family'],
        'q14' => 'yes_with_substitute',
        'q15a' => ['none'],
        'q15b' => 'yes',
        'q16' => 'yes',
    ], $overrides);
}
