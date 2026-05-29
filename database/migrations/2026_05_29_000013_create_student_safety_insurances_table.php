<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_safety_insurances')) {
            return;
        }

        Schema::create('student_safety_insurances', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh')->index();
            $table->string('student_name')->nullable();
            $table->string('grade')->nullable()->index();
            $table->string('education_length')->nullable();
            $table->string('college')->nullable()->index();
            $table->string('major')->nullable();
            $table->string('class_name')->nullable()->index();
            $table->unsignedSmallInteger('annual_year')->index();
            $table->boolean('is_insured')->default(false)->index();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['student_xgh', 'annual_year'], 'uniq_student_safety_student_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_safety_insurances');
    }
};
