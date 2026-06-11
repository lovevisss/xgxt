<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_access_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no')->unique();
            $table->string('teacher_name')->nullable();
            $table->string('unit_name')->nullable();
            $table->string('scope_name')->nullable();
            $table->string('department_code')->nullable()->index();
            $table->string('scope_type', 16)->default('college')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_access_permissions');
    }
};
