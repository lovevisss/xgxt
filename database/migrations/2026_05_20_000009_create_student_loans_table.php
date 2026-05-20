<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_loans', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh')->index();
            $table->string('student_name')->nullable();
            $table->string('id_card')->nullable()->index();
            $table->string('college')->nullable()->index();
            $table->string('class_name')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('annual_year')->index();
            $table->string('source', 64)->nullable()->index();
            $table->string('remark')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['student_xgh', 'annual_year'], 'idx_student_loans_student_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_loans');
    }
};
