<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * "This ran" markers.
         *
         * Deliberately a table and not the cache. The cache is cleared on every
         * deploy, and a wiped heartbeat is indistinguishable from a scheduler
         * that has stopped — which would show a false critical on the dashboard
         * after every release and train people to ignore it.
         */
        Schema::create('system_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->timestamp('ran_at');
            $table->string('status', 20)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        /*
         * The last state each check was in, so an alert fires once when it
         * crosses into critical rather than every time the check runs.
         */
        Schema::create('system_health_states', function (Blueprint $table) {
            $table->id();
            $table->string('check_key', 60)->unique();
            $table->string('state', 20);
            $table->timestamp('changed_at');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_health_states');
        Schema::dropIfExists('system_heartbeats');
    }
};
