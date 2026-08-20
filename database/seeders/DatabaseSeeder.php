<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Order matters: permissions and settings first, because everything
        // downstream reads them.
        $this->call([
            PermissionSeeder::class,
            SettingsSeeder::class,
            FeeAllocationSeeder::class,
            QuestionnaireSeeder::class,
            DetailedQuestionnaireSeeder::class,
            ContentSeeder::class,
            NotificationTemplateSeeder::class,
            AdminUserSeeder::class,
        ]);

        // Demo data is opt-in and never runs in production.
        if (app()->environment('local', 'development') && env('SEED_DEMO_DATA', false)) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
