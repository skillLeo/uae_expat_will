<?php

use App\Domain\Settings\Enums\SettingGroup;
use App\Domain\Settings\Enums\SettingType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Adds the Search Console file-verification setting to a live database.
 *
 * define() in the seeder only writes a default when the row does not exist,
 * and a deploy runs migrations rather than seeders, so a new setting reaches
 * production here or not at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('settings')->where('key', 'analytics.search_console_file')->exists()) {
            return;
        }

        DB::table('settings')->insert([
            'group' => SettingGroup::Analytics->value,
            'key' => 'analytics.search_console_file',
            'value' => '',
            'type' => SettingType::String->value,
            'label' => 'Search Console verification file',
            'help_text' => 'The filename Google gives you, e.g. google1a2b3c4d.html. It is then served at the site root automatically.',
            'is_public' => false,
            'order' => 4,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::flush();
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'analytics.search_console_file')->delete();
        Cache::flush();
    }
};
