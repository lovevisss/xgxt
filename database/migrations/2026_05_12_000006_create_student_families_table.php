<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_families', function (Blueprint $table) {
            $table->id();
            $table->string('sync_key', 32)->unique()->comment('源数据去重键');
            $table->string('stu_no')->index()->comment('学号');
            $table->string('name')->comment('姓名');
            $table->string('relationship')->nullable()->comment('关系');
            $table->string('specific_relationship')->nullable()->comment('具体关系(relationship=其他时)');
            $table->string('work_unit')->nullable()->comment('工作单位');
            $table->string('position')->nullable()->comment('职位');
            $table->string('phone')->nullable()->comment('手机');
            $table->boolean('is_emergency_contact')->default(false)->index()->comment('是否紧急联系人');
            $table->timestamp('synced_at')->nullable()->comment('最近同步时间');
            $table->boolean('is_local_modified')->default(false)->index()->comment('是否本地已修改');
            $table->timestamp('local_modified_at')->nullable()->comment('本地修改时间');
            $table->timestamps();

            $table->index(['stu_no', 'is_emergency_contact'], 'idx_student_families_stu_no_emergency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_families');
    }
};

