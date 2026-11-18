<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_lists', function (Blueprint $table) {
            $table->uuid('recurring_schedule_group_id')->nullable();
            $table->unique(['instructor_id', 'recurring_schedule_group_id'], 'class_list_offering_unique');
        });
    }

    public function down(): void
    {
        Schema::table('class_lists', function (Blueprint $table) {
            $table->dropUnique('class_list_offering_unique');
            $table->dropColumn('recurring_schedule_group_id');
        });
    }
};
