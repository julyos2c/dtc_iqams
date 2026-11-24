<?php

use App\Support\QrCredentialConstraint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        QrCredentialConstraint::ensure();
    }

    public function down(): void
    {
        throw new RuntimeException('Forward-only QR constraint. Restore a verified backup for a schema rollback.');
    }
};
