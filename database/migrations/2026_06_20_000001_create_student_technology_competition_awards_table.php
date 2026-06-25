<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('student_technology_competition_awards')) {
            return;
        }

        Schema::create('student_technology_competition_awards', function (Blueprint $table) {
            $table->id();
            $table->string('student_xgh', 32)->index();
            $table->string('student_name')->nullable();
            $table->string('college')->nullable()->index();
            $table->string('class_name')->nullable()->index();
            $table->string('grade')->nullable()->index();
            $table->string('award_name');
            $table->dateTime('awarded_at')->nullable()->index();
            $table->unsignedSmallInteger('annual_year')->nullable()->index();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['student_xgh', 'award_name', 'awarded_at'], 'uniq_student_tech_award');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_technology_competition_awards');
    }
};
