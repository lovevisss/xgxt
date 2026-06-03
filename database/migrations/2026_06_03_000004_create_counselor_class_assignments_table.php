<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counselor_class_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('class_code')->nullable()->index();
            $table->string('class_name')->index();
            $table->string('normalized_class_name')->index();
            $table->string('college_code')->nullable()->index();
            $table->string('college_name')->nullable()->index();
            $table->string('source')->default('manual')->index();
            $table->timestamps();

            $table->unique(['user_id', 'normalized_class_name'], 'uniq_counselor_class_user_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counselor_class_assignments');
    }
};
