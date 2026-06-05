<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_academic_year_averages', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh', 32)->index();
            $table->string('student_name')->nullable();
            $table->string('academic_year', 16)->index();
            $table->string('class_code')->nullable()->index();
            $table->string('class_name')->nullable();
            $table->string('major_code')->nullable()->index();
            $table->decimal('average_score', 6, 2)->nullable();
            $table->decimal('total_credits', 8, 2)->default(0);
            $table->unsignedInteger('course_count')->default(0);
            $table->unsignedInteger('class_rank')->nullable();
            $table->unsignedInteger('class_size')->nullable();
            $table->unsignedInteger('major_rank')->nullable();
            $table->unsignedInteger('major_size')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['student_xgh', 'academic_year'], 'uniq_student_year_average');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_academic_year_averages');
    }
};
