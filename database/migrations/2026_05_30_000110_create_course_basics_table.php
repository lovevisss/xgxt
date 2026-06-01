<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_basics', function (Blueprint $table) {
            $table->id();
            $table->string('kcbm', 64)->unique()->comment('课程编码');
            $table->string('kcmc')->nullable()->comment('课程名称');
            $table->json('raw')->nullable()->comment('中间库原始数据');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_basics');
    }
};

