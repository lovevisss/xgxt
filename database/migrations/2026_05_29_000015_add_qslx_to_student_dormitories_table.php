<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_dormitories')) {
            return;
        }

        if (! Schema::hasColumn('student_dormitories', 'qslx')) {
            Schema::table('student_dormitories', function (Blueprint $table) {
                $table->string('qslx')->nullable()->after('xz')->comment('寝室类型');
            });
        }

        if (Schema::hasColumn('student_dormitories', 'qsls')) {
            DB::table('student_dormitories')
                ->whereNull('qslx')
                ->update([
                    'qslx' => DB::raw('qsls'),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_dormitories')) {
            return;
        }

        if (Schema::hasColumn('student_dormitories', 'qslx')) {
            Schema::table('student_dormitories', function (Blueprint $table) {
                $table->dropColumn('qslx');
            });
        }
    }
};

