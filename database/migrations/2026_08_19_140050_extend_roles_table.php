<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('description')->nullable()->after('guard_name');
            // A system role is seeded and cannot be deleted from the UI. Super
            // Administrator must always exist or the platform can be locked out.
            $table->boolean('is_system')->default(false)->after('description');
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('description')->nullable()->after('guard_name');
            $table->string('module', 40)->nullable()->after('description')->index();
        });
    }

    public function down(): void
    {
        Schema::table('roles', fn (Blueprint $t) => $t->dropColumn(['description', 'is_system']));
        Schema::table('permissions', fn (Blueprint $t) => $t->dropColumn(['description', 'module']));
    }
};
