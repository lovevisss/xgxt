<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->text('exclude_reason')->nullable()->comment('暂不计入统计原因')->after('status');
            $table->dateTime('exclude_until')->nullable()->index()->comment('暂不计入统计截止时间')->after('exclude_reason');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['exclude_reason', 'exclude_until']);
        });
    }
};
