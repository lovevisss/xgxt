<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('passes', function (Blueprint $table) {
            $table->index(['is_recorded', 'gh', 'smsj'], 'idx_passes_recorded_gh_smsj');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->index(['exclude_until', 'last_smsj'], 'idx_students_exclude_last_smsj');
        });
    }

    public function down(): void
    {
        Schema::table('passes', function (Blueprint $table) {
            $table->dropIndex('idx_passes_recorded_gh_smsj');
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('idx_students_exclude_last_smsj');
        });
    }
};

