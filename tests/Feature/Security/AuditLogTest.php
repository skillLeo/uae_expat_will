<?php

use App\Domain\Settings\Services\SettingsRepository;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => seedPlatform());

it('blocks an UPDATE on the audit log at the database level', function () {
    activity('test')->log('Something happened');
    $id = DB::table('activity_log')->latest('id')->value('id');

    // Application code is exactly what an attacker replaces, so the guard that
    // matters is the one in the database itself.
    expect(fn () => DB::table('activity_log')->where('id', $id)->update(['description' => 'tampered']))
        ->toThrow(QueryException::class, 'append-only');

    expect(DB::table('activity_log')->where('id', $id)->value('description'))
        ->toBe('Something happened');
});

it('blocks a DELETE on the audit log at the database level', function () {
    activity('test')->log('Something happened');
    $before = DB::table('activity_log')->count();
    $id = DB::table('activity_log')->latest('id')->value('id');

    expect(fn () => DB::table('activity_log')->where('id', $id)->delete())
        ->toThrow(QueryException::class, 'append-only');

    expect(DB::table('activity_log')->count())->toBe($before);
});

it('blocks truncating the audit log', function () {
    activity('test')->log('Something happened');

    expect(fn () => DB::table('activity_log')->delete())->toThrow(QueryException::class, 'append-only');
    expect(DB::table('activity_log')->count())->toBeGreaterThan(0);
});

it('still allows appending', function () {
    $before = DB::table('activity_log')->count();

    activity('test')->log('One');
    activity('test')->log('Two');

    expect(DB::table('activity_log')->count())->toBe($before + 2);
});

it('logs a settings change with the actor', function () {
    $admin = adminUser();
    $this->actingAs($admin, 'admin');

    app(SettingsRepository::class)
        ->set('commercial.standard_fee', 2500);

    $entry = DB::table('activity_log')->where('log_name', 'settings')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->causer_id)->toBe($admin->id);
});

it('never writes a secret value into settings history', function () {
    $this->actingAs(adminUser(), 'admin');

    app(SettingsRepository::class)
        ->set('payment.auth_key', 'super-secret-key-value');

    $history = DB::table('settings_history')
        ->where('setting_id', Setting::where('key', 'payment.auth_key')->value('id'))
        ->latest('id')->first();

    // The history table must not become a plaintext archive of every credential
    // the platform has ever held.
    expect($history->new_value)->toBe('••••••••')
        ->and($history->new_value)->not->toContain('super-secret');
});
