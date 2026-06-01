<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_dormitories', function (Blueprint $table) {
            $table->id();
            $table->string('xh')->unique()->comment('学号');
            $table->string('xm')->nullable()->comment('姓名');
            $table->string('xy')->nullable()->comment('学院');
            $table->string('zy')->nullable()->comment('专业');
            $table->string('bj')->nullable()->comment('班级');
            $table->string('nj')->nullable()->comment('年级');
            $table->string('ssh')->nullable()->index()->comment('宿舍号');
            $table->string('ch')->nullable()->comment('床位号');
            $table->string('xz')->nullable()->comment('学制');
            $table->string('qslx')->nullable()->comment('寝室类型');
            $table->string('xb')->nullable()->comment('性别');
            $table->string('source_table')->nullable()->comment('来源表');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_dormitories');
    }
};

