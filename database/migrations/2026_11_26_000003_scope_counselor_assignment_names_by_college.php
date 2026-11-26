<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counselor_class_assignments', function (Blueprint $table) {
            $table->unique(['user_id', 'college_code', 'normalized_class_name'], 'uniq_counselor_user_college_name');
            $table->unique(['counselor_cas_username', 'college_code', 'normalized_class_name'], 'uniq_counselor_cas_college_name');
        });
        Schema::table('counselor_class_assignments', function (Blueprint $table) {
            $table->dropUnique('uniq_counselor_class_user_name');
            $table->dropUnique('uniq_counselor_class_cas_name');
        });
    }

    public function down(): void
    {
        Schema::table('counselor_class_assignments', function (Blueprint $table) {
            $table->unique(['user_id', 'normalized_class_name'], 'uniq_counselor_class_user_name');
            $table->unique(['counselor_cas_username', 'normalized_class_name'], 'uniq_counselor_class_cas_name');
        });
        Schema::table('counselor_class_assignments', function (Blueprint $table) {
            $table->dropUnique('uniq_counselor_user_college_name');
            $table->dropUnique('uniq_counselor_cas_college_name');
        });
    }
};
