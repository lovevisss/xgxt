<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_physical_tests')) {
            return;
        }

        Schema::create('student_physical_tests', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh')->index();
            $table->string('student_name')->nullable();
            $table->string('gender')->nullable();
            $table->string('college')->nullable()->index();
            $table->string('class_name')->nullable()->index();
            $table->string('academic_year', 16)->index();
            $table->decimal('score', 5, 1)->nullable();
            $table->string('remark')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['student_xgh', 'academic_year'], 'uniq_student_physical_student_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_physical_tests');
    }
};
