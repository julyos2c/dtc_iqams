<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_excuse_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('reason_type');
            $table->text('explanation');
            $table->string('attachment_path')->nullable();
            $table->timestamps();
        });
        Schema::create('student_excuse_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_excuse_request_id')->constrained('student_excuse_requests');
            $table->foreignId('attendance_log_id')->constrained();
            $table->foreignId('instructor_id')->constrained();
            $table->string('status')->default('pending');
            $table->text('remarks')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
            $table->index(['attendance_log_id', 'status']);
            $table->index(['instructor_id', 'status']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('student_excuse_decisions');
        Schema::dropIfExists('student_excuse_requests');
    }
};
