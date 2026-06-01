<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_course_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('xnxq')->index()->comment('学年学期');
            $table->string('xh')->index()->comment('学号');
            $table->string('pkbh')->comment('排课编号');
            $table->string('kkyxbm')->nullable();
            $table->string('kkzybm')->nullable();
            $table->string('kkbjbm')->nullable();
            $table->string('kcbm')->nullable()->index();
            $table->string('zc')->nullable();
            $table->unsignedSmallInteger('qsz')->nullable()->index();
            $table->unsignedSmallInteger('zzz')->nullable()->index();
            $table->string('dsz')->nullable();
            $table->unsignedTinyInteger('xqj')->nullable()->index();
            $table->string('jc')->nullable();
            $table->string('sksj')->nullable();
            $table->string('jxdd')->nullable();
            $table->string('jslxm')->nullable();
            $table->string('xf')->nullable();
            $table->string('llxs')->nullable();
            $table->string('syxs')->nullable();
            $table->string('sjxs')->nullable();
            $table->string('zxs')->nullable();
            $table->string('skjsgh')->nullable();
            $table->string('skjsxm')->nullable();
            $table->string('kcxzm')->nullable();
            $table->string('kcsxm')->nullable()->index();
            $table->string('kslbm')->nullable();
            $table->string('ksfsm')->nullable();
            $table->string('ksxzm')->nullable();
            $table->string('weekday_label')->nullable();
            $table->unsignedTinyInteger('weekday')->nullable()->index();
            $table->unsignedTinyInteger('period_start')->nullable();
            $table->unsignedTinyInteger('period_end')->nullable();
            $table->unsignedSmallInteger('week_start')->nullable()->index();
            $table->unsignedSmallInteger('week_end')->nullable()->index();
            $table->string('week_pattern', 16)->nullable()->index();
            $table->string('tstamp')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['xnxq', 'xh', 'pkbh'], 'uniq_student_course_xnxq_xh_pkbh');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_course_schedules');
    }
};
