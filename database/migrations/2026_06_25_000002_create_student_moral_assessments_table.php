<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_moral_assessments')) {
            return;
        }

        Schema::create('student_moral_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh', 32)->index();
            $table->string('student_name')->nullable();
            $table->string('academic_year', 16)->index();
            $table->string('semester', 16)->index();
            $table->string('college')->nullable()->index();
            $table->string('class_name')->nullable()->index();
            $table->unsignedInteger('rank')->nullable();
            $table->decimal('base_score', 6, 3)->nullable();
            $table->decimal('bonus_score', 6, 3)->nullable();
            $table->decimal('deduction_score', 6, 3)->nullable();
            $table->decimal('total_score', 6, 3)->nullable();
            $table->string('remark')->nullable();
            $table->string('source_sheet')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['student_xgh', 'academic_year', 'semester'], 'uniq_student_moral_student_year_term');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_moral_assessments');
    }
};
