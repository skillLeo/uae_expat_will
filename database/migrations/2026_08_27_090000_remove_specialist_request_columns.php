<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the specialist-request columns.
 *
 * They were added on 26 August for a "Specialist Legal Review Request Flow"
 * handoff covering DIFC, existing-Will and estate enquiries. That document
 * turned out to belong to a different client's project, so none of it applies
 * here and all of it comes out.
 *
 * A migration rather than an edit to the original, because the original had
 * already run on production. Rewriting a migration that a live database has
 * executed leaves the two permanently out of step.
 *
 * Guarded on hasColumn so it is safe on a database that never got them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            if (Schema::hasColumn('cases', 'request_type')) {
                $table->dropIndex(['request_type']);
                $table->dropColumn('request_type');
            }

            if (Schema::hasColumn('cases', 'brief_description')) {
                $table->dropColumn('brief_description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->string('request_type', 40)->default('standard_will')->after('pathway')->index();
            $table->text('brief_description')->nullable()->after('service_type');
        });
    }
};
