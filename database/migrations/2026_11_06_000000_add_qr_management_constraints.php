<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('qr_credentials', function (Blueprint $table) {
            $table->string('revocation_reason')->nullable();
        });
        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE qr_credentials ADD active_user_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN user_id ELSE NULL END) STORED, ADD UNIQUE INDEX qr_one_active_user (active_user_id)");
        } elseif ($driver === 'sqlite') {
            DB::statement("CREATE UNIQUE INDEX qr_one_active_user ON qr_credentials (user_id) WHERE status = 'active'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE qr_credentials DROP INDEX qr_one_active_user, DROP COLUMN active_user_id');
        } elseif ($driver === 'sqlite') {
            DB::statement('DROP INDEX qr_one_active_user');
        }
        Schema::table('qr_credentials', fn (Blueprint $table) => $table->dropColumn('revocation_reason'));
    }
};
