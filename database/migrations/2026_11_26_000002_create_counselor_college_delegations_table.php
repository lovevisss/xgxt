<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counselor_college_delegations', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no');
            $table->string('college_code');
            $table->string('college_name');
            $table->string('reason')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_no', 'college_code']);
            $table->index(['college_code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counselor_college_delegations');
    }
};
