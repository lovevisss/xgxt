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
            $table->char('class_identity_hash', 64)->nullable()->unique();
        });

        $rows = DB::table('counselor_class_assignments')->get(['id', 'college_code', 'class_code', 'normalized_class_name']);
        $groups = $rows->groupBy(fn ($row) => hash('sha256', json_encode([
            $row->college_code, filled($row->class_code) ? 'code' : 'name',
            filled($row->class_code) ? trim($row->class_code) : $row->normalized_class_name,
        ], JSON_UNESCAPED_UNICODE)));
        foreach ($groups as $hash => $assignments) {
            if ($assignments->count() === 1 && filled($assignments->first()->college_code)) {
                DB::table('counselor_class_assignments')->where('id', $assignments->first()->id)->update(['class_identity_hash' => $hash]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('counselor_class_assignments', fn (Blueprint $table) => $table->dropColumn('class_identity_hash'));
    }
};
