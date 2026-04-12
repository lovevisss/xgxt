<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dateTime('last_smsj')->nullable()->index()->comment('最近刷码时间')->after('updated_at');
            $table->string('status', 20)->default('normal')->index()->comment('学生状态 normal/lost')->after('last_smsj');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['last_smsj', 'status']);
        });
    }
};
