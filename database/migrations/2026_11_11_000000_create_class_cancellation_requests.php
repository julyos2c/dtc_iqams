<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // MySQL DDL is not transactional: a failed index creation can leave
        // the table and foreign keys behind without recording the migration.
        if (Schema::hasTable('class_cancellation_requests')) {
            if (! Schema::hasIndex('class_cancellation_requests', 'class_cancellation_requests_active_key_unique')) {
                Schema::table('class_cancellation_requests', fn (Blueprint $table) => $table->unique('active_key'));
            }
            if (! Schema::hasIndex('class_cancellation_requests', 'class_cancel_occurrence_status_idx')) {
                Schema::table('class_cancellation_requests', fn (Blueprint $table) => $table->index(['schedule_id', 'occurrence_date', 'status'], 'class_cancel_occurrence_status_idx'));
            }
            return;
        }
        Schema::create('class_cancellation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained()->restrictOnDelete();
            $table->date('occurrence_date');
            $table->dateTime('opens_at');
            $table->dateTime('ends_at');
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->string('active_key')->nullable()->unique();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['schedule_id', 'occurrence_date', 'status'], 'class_cancel_occurrence_status_idx');
        });
    }

    public function down(): void { Schema::dropIfExists('class_cancellation_requests'); }
};
