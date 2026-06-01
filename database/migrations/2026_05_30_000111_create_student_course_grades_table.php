<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_course_grades', function (Blueprint $table) {
            $table->id();
            $table->string('xh', 32)->index()->comment('学号');
            $table->string('xnxq', 32)->index()->comment('学年学期');
            $table->string('kcbm', 64)->index()->comment('课程编码');
            $table->string('kcmc')->nullable()->comment('课程名称');
            $table->string('cj', 32)->nullable()->comment('成绩');
            $table->decimal('jd', 5, 2)->nullable()->comment('绩点');
            $table->decimal('xf', 6, 2)->nullable()->comment('学分');
            $table->string('ksxz', 32)->default('')->comment('考试性质');
            $table->json('raw')->nullable()->comment('中间库原始数据');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['xh', 'xnxq', 'kcbm', 'ksxz'], 'uniq_xh_xnxq_kcbm_ksxz');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_course_grades');
    }
};

