<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counselor_class_assignments', function (Blueprint $table) {
            $table->string('counselor_cas_username')->nullable()->after('user_id')->index();
        });

        DB::table('counselor_class_assignments')
            ->whereNull('counselor_cas_username')
            ->orderBy('id')
            ->get(['id', 'user_id'])
            ->each(function ($assignment): void {
                $casUsername = DB::table('users')->where('id', $assignment->user_id)->value('cas_username');

                if ($casUsername) {
                    DB::table('counselor_class_assignments')
                        ->where('id', $assignment->id)
                        ->update(['counselor_cas_username' => $casUsername]);
                }
            });

        Schema::table('counselor_class_assignments', function (Blueprint $table) {
            $table->unique(['counselor_cas_username', 'normalized_class_name'], 'uniq_counselor_class_cas_name');
        });
    }

    public function down(): void
    {
        Schema::table('counselor_class_assignments', function (Blueprint $table) {
            $table->dropUnique('uniq_counselor_class_cas_name');
            $table->dropColumn('counselor_cas_username');
        });
    }
};
