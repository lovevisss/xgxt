<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_education_histories', function (Blueprint $table) {
            $table->id();
            $table->string('source_id')->unique();
            $table->string('stu_no')->index();
            $table->string('qualifications')->nullable();
            $table->string('start_year', 16)->nullable();
            $table->string('end_year', 16)->nullable();
            $table->string('school_name')->nullable();
            $table->integer('sort')->nullable();
            $table->timestamp('source_created_at')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['stu_no', 'sort'], 'idx_student_education_histories_stu_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_education_histories');
    }
};
