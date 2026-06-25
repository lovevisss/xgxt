<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_academic_year_averages', function (Blueprint $table) {
            $table->id()->comment('主键ID');
            $table->string('student_xgh', 32)->index()->comment('学生学号');
            $table->string('student_name')->nullable()->comment('学生姓名');
            $table->string('academic_year', 16)->index()->comment('学年，如 2025-2026');
            $table->string('class_code')->nullable()->index()->comment('班级代码');
            $table->string('class_name')->nullable()->comment('班级名称');
            $table->string('major_code')->nullable()->index()->comment('专业代码');
            $table->decimal('average_score', 6, 2)->nullable()->comment('学年学习平均成绩');
            $table->decimal('total_credits', 8, 2)->default(0)->comment('参与计算的总学分');
            $table->unsignedInteger('course_count')->default(0)->comment('参与计算的课程数量');
            $table->unsignedInteger('class_rank')->nullable()->comment('班级排名');
            $table->unsignedInteger('class_size')->nullable()->comment('班级参与排名人数');
            $table->unsignedInteger('major_rank')->nullable()->comment('专业排名');
            $table->unsignedInteger('major_size')->nullable()->comment('专业参与排名人数');
            $table->timestamp('calculated_at')->nullable()->comment('计算时间');
            $table->timestamps();

            $table->unique(['student_xgh', 'academic_year'], 'uniq_student_year_average');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_academic_year_averages');
    }
};
