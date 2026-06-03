<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_classes', function (Blueprint $table) {
            $table->id();
            $table->string('class_code')->unique();
            $table->string('class_name')->index();
            $table->string('major_code')->nullable()->index();
            $table->string('grade')->nullable()->index();
            $table->string('built_at')->nullable();
            $table->string('standard_student_number')->nullable();
            $table->string('source_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_classes');
    }
};
