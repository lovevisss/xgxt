<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->string('xgh')->primary()->comment('工号');
            $table->string('xm')->comment('姓名');
            $table->string('xbm')->comment('性别码');
            $table->string('rylx')->comment('人员类型');
            $table->string('dwmc')->comment('单位名称');
            $table->string('dwbm')->comment('单位编码');
            $table->string('bjbm')->nullable()->comment('班级编码');
            $table->string('bjmc')->nullable()->comment('班级名称');
            $table->string('dzyx')->nullable()->comment('电子邮箱');
            $table->string('yddh')->nullable()->comment('移动电话');
            $table->string('csrq')->nullable()->comment('出生日期');
            $table->string('jg')->nullable()->comment('籍贯码');
            $table->string('mzm')->nullable()->comment('民族码');
            $table->string('sfzjh')->nullable()->comment('身份证件号');
            $table->string('politicalcode')->nullable()->comment('政治面貌码');
            $table->string('zgxl')->nullable()->comment('最高学历');
            $table->string('wlkh')->nullable()->comment('物理卡号');
            $table->string('zhbz')->nullable()->comment('账户标志');
            $table->timestamp('updated_at')->useCurrent()->comment('更新时间');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
