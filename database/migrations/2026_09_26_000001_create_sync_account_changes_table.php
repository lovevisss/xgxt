<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_account_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_task_id')->constrained('sync_tasks')->cascadeOnDelete();
            $table->string('student_xgh', 32);
            $table->string('student_name');
            $table->string('college_name')->nullable();
            $table->date('previous_expiry_date')->nullable();
            $table->date('new_expiry_date')->nullable();
            $table->string('previous_state', 20);
            $table->string('new_state', 20);
            $table->timestamp('changed_at');

            $table->unique(['sync_task_id', 'student_xgh']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_account_changes');
    }
};
