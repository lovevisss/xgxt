<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_cadre_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh')->index();
            $table->string('student_name')->nullable();
            $table->string('academic_year')->index();
            $table->string('semester')->nullable();
            $table->string('organization')->nullable();
            $table->string('department')->nullable();
            $table->string('position');
            $table->decimal('self_score', 6, 2)->nullable();
            $table->decimal('peer_score', 6, 2)->nullable();
            $table->decimal('advisor_score', 6, 2)->nullable();
            $table->decimal('department_score', 6, 2)->nullable();
            $table->decimal('total_score', 6, 2)->nullable();
            $table->string('grade')->nullable();
            $table->string('source_file')->nullable();
            $table->string('sync_key')->unique();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });

        Schema::create('student_cadre_assessment_matches', function (Blueprint $table) {
            $table->id();
            $table->string('student_name')->index();
            $table->string('academic_year')->index();
            $table->string('semester')->nullable();
            $table->string('organization')->nullable();
            $table->string('department')->nullable();
            $table->string('position');
            $table->decimal('self_score', 6, 2)->nullable();
            $table->decimal('peer_score', 6, 2)->nullable();
            $table->decimal('advisor_score', 6, 2)->nullable();
            $table->decimal('department_score', 6, 2)->nullable();
            $table->decimal('total_score', 6, 2)->nullable();
            $table->string('grade')->nullable();
            $table->string('source_file')->nullable();
            $table->json('candidate_students')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('resolved_student_xgh')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_cadre_assessment_matches');
        Schema::dropIfExists('student_cadre_assessments');
    }
};
