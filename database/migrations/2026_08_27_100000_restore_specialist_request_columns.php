<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings back the two columns an existing-Will or estate request needs.
 *
 * They were removed earlier today along with a handoff that belonged to
 * another client's project. That removal went too far: routing those two
 * enquiries to a request form instead of a rejection page was Ahmed's own
 * instruction on 25 August — "number 4 and 5 no questions at all, goes to
 * contact team" — and predates that document entirely.
 *
 * `request_type` carries no DIFC value. DIFC stays with the other project.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            if (! Schema::hasColumn('cases', 'request_type')) {
                $table->string('request_type', 40)->default('standard_will')->after('pathway')->index();
            }

            if (! Schema::hasColumn('cases', 'brief_description')) {
                $table->text('brief_description')->nullable()->after('service_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['request_type']);
            $table->dropColumn(['request_type', 'brief_description']);
        });
    }
};
