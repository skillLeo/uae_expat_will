<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // `user_type` is the discriminator. Customers and Summit staff live in one
            // table but authenticate through separate guards (`web` and `admin`), and
            // every query is scoped by type. See config/auth.php.
            $table->string('user_type', 20)->default('customer')->after('id')->index();
            $table->string('phone', 32)->nullable()->after('email');

            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('disabled_at')->nullable();
            $table->foreignId('disabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disabled_reason')->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->unsignedInteger('failed_login_count')->default(0);
            $table->timestamp('locked_until')->nullable();

            $table->string('timezone', 64)->default('Asia/Dubai');
            $table->string('locale', 10)->default('en');

            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disabled_by');
            $table->dropColumn([
                'user_type', 'phone', 'two_factor_secret', 'two_factor_recovery_codes',
                'two_factor_confirmed_at', 'is_active', 'disabled_at', 'disabled_reason',
                'last_login_at', 'last_login_ip', 'failed_login_count', 'locked_until',
                'timezone', 'locale', 'deleted_at',
            ]);
        });
    }
};
