<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class QrCredentialConstraint
{
    public static function ensure(): void
    {
        $driver = DB::getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new RuntimeException('Unsupported QR database driver.');
        }
        if (DB::table('qr_credentials')->select('user_id')->where('status', 'active')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate active QR credentials require review.');
        }
        if ($driver === 'sqlite') {
            $sql = DB::table('sqlite_master')->where('name', 'qr_one_active_user')->value('sql');
            $expected = "CREATE UNIQUE INDEX qr_one_active_user ON qr_credentials (user_id) WHERE status = 'active'";
            if ($sql !== null && $sql !== $expected) {
                throw new RuntimeException('Unexpected SQLite QR index.');
            }
            self::addReason();
            if ($sql === null) {
                DB::statement($expected);
            }

            return;
        }

        $table = "table_schema=DATABASE() AND table_name='qr_credentials'";
        $checks = DB::selectOne('SELECT @@session.foreign_key_checks AS fk_checks, @@session.unique_checks AS uq_checks');
        if (DB::selectOne("SELECT engine AS engine_name FROM information_schema.tables WHERE $table")?->engine_name !== 'InnoDB'
            || (int) $checks->fk_checks !== 1 || (int) $checks->uq_checks !== 1) {
            throw new RuntimeException('QR repair requires InnoDB with foreign key and uniqueness checks enabled.');
        }
        $fk = DB::select("SELECT k.column_name c,k.referenced_column_name p,k.referenced_table_name t,r.update_rule u,r.delete_rule d
            FROM information_schema.key_column_usage k JOIN information_schema.referential_constraints r
            ON r.constraint_schema=k.constraint_schema AND r.constraint_name=k.constraint_name AND r.table_name=k.table_name
            WHERE k.table_schema=DATABASE() AND k.table_name='qr_credentials' AND k.constraint_name='qr_credentials_user_id_foreign'
            AND k.referenced_table_schema=DATABASE()");
        if (count($fk) !== 1 || array_values((array) $fk[0]) !== ['user_id', 'id', 'users', 'CASCADE', 'RESTRICT']) {
            throw new RuntimeException('Unexpected QR owner foreign key.');
        }
        $columns = collect(DB::select("SELECT column_name n,column_type typ,is_nullable nullable,extra AS extra,generation_expression expr
            FROM information_schema.columns WHERE $table"))->keyBy('n');
        $reason = $columns->get('revocation_reason');
        if ($reason && ($reason->typ !== 'varchar(255)' || $reason->nullable !== 'YES' || $reason->extra !== '')) {
            throw new RuntimeException('Unexpected revocation_reason definition.');
        }
        $generated = $columns->get('active_user_id');
        if ($generated) {
            $expr = str_replace(["_utf8mb4\\'active\\'", "_utf8mb4'active'"], "'active'", $generated->expr);
            if (! preg_match('/^bigint(?:\(\d+\))? unsigned$/D', $generated->typ) || $generated->nullable !== 'YES'
                || ! in_array($generated->extra, ['VIRTUAL GENERATED', 'STORED GENERATED'], true)
                || ! str_contains($expr, "'active'") || preg_replace('/[\x60()\s]+/', '', $expr) !== "casewhenstatus='active'thenuser_idelseNULLend") {
                throw new RuntimeException('Unexpected active_user_id definition.');
            }
        }
        $indexes = DB::select("SELECT column_name c,non_unique n,sub_part prefix_length FROM information_schema.statistics
            WHERE $table AND index_name='qr_one_active_user'");
        if ($indexes && (! $generated || count($indexes) !== 1 || $indexes[0]->c !== 'active_user_id'
            || (int) $indexes[0]->n !== 0 || $indexes[0]->prefix_length !== null)) {
            throw new RuntimeException('Unexpected active QR index.');
        }
        $actions = [];
        if ($generated && $generated->extra === 'STORED GENERATED') {
            $dependencies = DB::select("SELECT index_name FROM information_schema.statistics WHERE $table
                AND column_name='active_user_id' AND index_name<>'qr_one_active_user'");
            if ($dependencies) {
                throw new RuntimeException('Additional indexes depend on active_user_id.');
            }
            if ($indexes) {
                $actions[] = 'DROP INDEX qr_one_active_user';
            }
            $actions[] = 'DROP COLUMN active_user_id';
            $generated = null;
            $indexes = [];
        }
        if (! $generated) {
            $actions[] = "ADD COLUMN active_user_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status='active' THEN user_id ELSE NULL END) VIRTUAL";
        }
        if (! $indexes) {
            $actions[] = 'ADD UNIQUE INDEX qr_one_active_user(active_user_id)';
        }
        self::addReason();
        if ($actions) {
            DB::statement('ALTER TABLE qr_credentials '.implode(', ', $actions));
        }
    }

    private static function addReason(): void
    {
        if (! Schema::hasColumn('qr_credentials', 'revocation_reason')) {
            Schema::table('qr_credentials', fn (Blueprint $table) => $table->string('revocation_reason')->nullable());
        }
    }
}
