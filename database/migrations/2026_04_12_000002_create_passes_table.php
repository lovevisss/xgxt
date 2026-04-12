<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('passes', function (Blueprint $table) {
            $table->id();
            $table->string('gh')->index()->comment('学工号');
            $table->string('xm')->nullable()->comment('姓名');
            $table->string('device')->nullable()->comment('设备编码');
            $table->string('smdd')->nullable()->comment('扫码地点');
            $table->dateTime('smsj')->index()->comment('扫码时间');
            $table->string('sblx')->nullable()->comment('扫码类型');
            $table->string('crlx')->nullable()->comment('出入类型');
            $table->boolean('is_recorded')->default(false)->index()->comment('是否已对照写入学生记录');
            $table->dateTime('recorded_at')->nullable()->comment('对照写入时间');
            $table->dateTime('synced_at')->nullable()->comment('同步时间');
            $table->timestamps();

            $table->unique(['gh', 'smsj', 'device'], 'uniq_pass_gh_smsj_device');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passes');
    }
};
