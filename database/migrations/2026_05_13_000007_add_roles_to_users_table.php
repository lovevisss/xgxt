<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cas_username')->nullable()->unique()->after('id');
            $table->string('role', 32)->default('staff')->index()->after('password');
            $table->string('dwbm')->nullable()->index()->after('role');
            $table->string('dwmc')->nullable()->after('dwbm');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['cas_username']);
            $table->dropColumn(['cas_username', 'role', 'dwbm', 'dwmc']);
        });
    }
};
