<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_medical_insurances')) {
            return;
        }

        Schema::create('student_medical_insurances', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh')->index();
            $table->string('student_name')->nullable();
            $table->string('insured_area')->nullable()->index();
            $table->date('enrolled_on')->nullable();
            $table->string('insurance_type')->nullable();
            $table->string('insurance_status')->nullable()->index();
            $table->string('identity_type')->nullable();
            $table->unsignedSmallInteger('annual_year')->index();
            $table->boolean('has_paid')->default(false)->index();
            $table->string('payment_start_month', 6)->nullable();
            $table->string('payment_end_month', 6)->nullable();
            $table->string('payment_type')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['student_xgh', 'annual_year'], 'uniq_student_medical_student_year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_medical_insurances');
    }
};
