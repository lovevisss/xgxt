<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_support_recipients', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh')->index();
            $table->string('student_name')->nullable();
            $table->string('gender', 16)->nullable();
            $table->string('college')->nullable()->index();
            $table->string('major')->nullable();
            $table->string('support_level', 32)->index();
            $table->string('academic_year', 16)->index();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['student_xgh', 'academic_year'], 'uniq_student_support_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_support_recipients');
    }
};
