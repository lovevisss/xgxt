<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_comprehensive_assessments')) {
            return;
        }

        Schema::create('student_comprehensive_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh', 32)->index();
            $table->string('student_name')->nullable();
            $table->string('academic_year', 16)->index();
            $table->string('college')->nullable()->index();
            $table->string('class_name')->nullable()->index();
            $table->unsignedInteger('rank')->nullable();
            $table->decimal('total_score', 6, 3)->nullable();
            $table->decimal('moral_score', 6, 3)->nullable();
            $table->decimal('intellectual_score', 6, 3)->nullable();
            $table->decimal('physical_score', 6, 3)->nullable();
            $table->decimal('aesthetic_score', 6, 3)->nullable();
            $table->decimal('labor_score', 6, 3)->nullable();
            $table->string('source_sheet')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['student_xgh', 'academic_year'], 'uniq_student_comprehensive_student_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_comprehensive_assessments');
    }
};
