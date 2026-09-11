<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counselor_management_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no')->unique();
            $table->string('teacher_name');
            $table->boolean('all_colleges')->default(false);
            $table->json('department_codes');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('counselor_management_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor')->nullable();
            $table->string('action');
            $table->string('subject');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counselor_management_logs');
        Schema::dropIfExists('counselor_management_permissions');
    }
};
