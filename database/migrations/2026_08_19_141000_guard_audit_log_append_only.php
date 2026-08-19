<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The audit log is APPEND ONLY, and that is enforced three ways:
 *
 *   1. No update or delete route exists. Admins get a viewer and an export.
 *   2. The Activity model refuses to save when it already exists, and refuses
 *      to be deleted (see App\Models\AuditActivity).
 *   3. This migration — a guard in the database itself, so that even a direct
 *      SQL connection, a future careless migration or a compromised application
 *      credential cannot quietly rewrite history.
 *
 * Layer 3 is the one that matters. The first two are application code, and
 * application code is exactly what an attacker replaces.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        match ($driver) {
            'sqlite' => $this->sqlite(),
            'mysql', 'mariadb' => $this->mysql(),
            'pgsql' => $this->pgsql(),
            default => null, // unsupported driver: layers 1 and 2 still apply
        };
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS activity_log_no_update ON activity_log');
            DB::unprepared('DROP TRIGGER IF EXISTS activity_log_no_delete ON activity_log');
            DB::unprepared('DROP FUNCTION IF EXISTS activity_log_immutable()');

            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS activity_log_no_update');
        DB::unprepared('DROP TRIGGER IF EXISTS activity_log_no_delete');
    }

    private function sqlite(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER activity_log_no_update
            BEFORE UPDATE ON activity_log
            BEGIN
                SELECT RAISE(ABORT, 'activity_log is append-only');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER activity_log_no_delete
            BEFORE DELETE ON activity_log
            BEGIN
                SELECT RAISE(ABORT, 'activity_log is append-only');
            END;
        SQL);
    }

    private function mysql(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER activity_log_no_update
            BEFORE UPDATE ON activity_log
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'activity_log is append-only';
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER activity_log_no_delete
            BEFORE DELETE ON activity_log
            FOR EACH ROW
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'activity_log is append-only';
        SQL);
    }

    private function pgsql(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION activity_log_immutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'activity_log is append-only';
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        DB::unprepared('CREATE TRIGGER activity_log_no_update BEFORE UPDATE ON activity_log FOR EACH ROW EXECUTE FUNCTION activity_log_immutable()');
        DB::unprepared('CREATE TRIGGER activity_log_no_delete BEFORE DELETE ON activity_log FOR EACH ROW EXECUTE FUNCTION activity_log_immutable()');
    }
};
