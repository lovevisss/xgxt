<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_sections', function (Blueprint $table) {
            $table->id();
            $table->string('jxb_id')->unique()->comment('教学班ID');
            $table->string('kkzt')->nullable();
            $table->string('kch')->nullable()->index();
            $table->string('kcmc')->nullable()->index();
            $table->string('xf')->nullable();
            $table->string('jxbmc')->nullable();
            $table->string('kclb')->nullable();
            $table->string('kcxz')->nullable();
            $table->string('kcgs')->nullable();
            $table->string('kkxiaoq')->nullable();
            $table->string('ktrl')->nullable();
            $table->string('yxrs')->nullable();
            $table->string('zxs')->nullable();
            $table->string('jgh')->nullable();
            $table->string('rkjs')->nullable();
            $table->string('jszc')->nullable();
            $table->string('sksj')->nullable();
            $table->string('jxdd')->nullable();
            $table->string('lh')->nullable();
            $table->string('khfs')->nullable();
            $table->string('ksxs')->nullable();
            $table->string('kkxy')->nullable()->index();
            $table->string('hbxx')->nullable();
            $table->string('xn')->nullable()->index();
            $table->string('xq')->nullable()->index();
            $table->string('qsjsz')->nullable();
            $table->unsignedTinyInteger('weekday')->nullable()->index();
            $table->unsignedTinyInteger('period_start')->nullable();
            $table->unsignedTinyInteger('period_end')->nullable();
            $table->unsignedSmallInteger('week_start')->nullable()->index();
            $table->unsignedSmallInteger('week_end')->nullable()->index();
            $table->string('week_pattern', 16)->nullable()->index();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_sections');
    }
};
