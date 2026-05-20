<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_awards', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh')->index();
            $table->string('student_name')->nullable();
            $table->string('award_name');
            $table->unsignedSmallInteger('annual_year')->index();
            $table->string('level', 32)->nullable()->index();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['student_xgh', 'annual_year'], 'idx_student_awards_student_year');
        });

        Schema::create('student_punishments', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh')->index();
            $table->string('student_name')->nullable();
            $table->string('reason');
            $table->date('punished_at')->nullable()->index();
            $table->unsignedSmallInteger('annual_year')->index();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['student_xgh', 'annual_year'], 'idx_student_punishments_student_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_punishments');
        Schema::dropIfExists('student_awards');
    }
};
