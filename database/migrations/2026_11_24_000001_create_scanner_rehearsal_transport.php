<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scanner_devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('terminal_id')->constrained('scanner_terminals');
            $t->foreignId('paired_by')->constrained('users');
            $t->char('fingerprint', 64)->unique();
            $t->text('public_key');
            $t->timestamp('paired_at');
            $t->timestamp('revoked_at')->nullable();
            $t->index(['terminal_id', 'revoked_at']);
        });
        Schema::create('scanner_device_challenges', function (Blueprint $t) {
            $t->char('nonce', 64)->primary();
            $t->foreignId('terminal_id')->constrained('scanner_terminals');
            $t->foreignId('operator_id')->constrained('users');
            $t->char('login_hash', 64);
            $t->string('purpose', 16);
            $t->timestamp('expires_at')->index();
        });
        Schema::create('scanner_offline_sessions', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('device_id')->constrained('scanner_devices');
            $t->foreignId('operator_id')->constrained('users');
            $t->uuid('clock_id');
            $t->unsignedBigInteger('anchor_ms');
            $t->unsignedBigInteger('expires_ms');
            $t->timestamp('revoked_at')->nullable();
            $t->unsignedBigInteger('ack_sequence')->default(0);
        });
        Schema::create('scanner_rehearsal_receipts', function (Blueprint $t) {
            $t->id();
            $t->uuid('event_uuid')->unique();
            $t->foreignUuid('session_id')->constrained('scanner_offline_sessions');
            $t->unsignedBigInteger('sequence');
            $t->char('payload_hash', 64);
            $t->unsignedBigInteger('captured_ms');
            $t->string('status', 24);
            $t->string('reason', 40)->nullable();
            $t->timestamp('received_at');
            $t->unique(['session_id', 'sequence']);
        });
        Schema::create('scanner_receipt_conflicts', function (Blueprint $t) {
            $t->id();
            $t->foreignUuid('session_id')->constrained('scanner_offline_sessions');
            $t->uuid('event_uuid')->index();
            $t->char('payload_hash', 64)->unique();
            $t->string('reason', 32);
            $t->timestamp('received_at');
        });
        Schema::table('scanner_terminals', function (Blueprint $t) {
            $t->timestamp('last_heartbeat_at')->nullable();
            $t->unsignedInteger('reported_pending')->nullable();
            $t->timestamp('last_rehearsal_sync_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scanner_receipt_conflicts');
        Schema::dropIfExists('scanner_rehearsal_receipts');
        Schema::dropIfExists('scanner_offline_sessions');
        Schema::dropIfExists('scanner_device_challenges');
        Schema::dropIfExists('scanner_devices');
        Schema::table('scanner_terminals', function (Blueprint $t) {
            $t->dropColumn(['last_heartbeat_at', 'reported_pending', 'last_rehearsal_sync_at']);
        });
    }
};
