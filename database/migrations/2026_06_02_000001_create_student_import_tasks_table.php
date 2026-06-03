<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_import_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('status')->default('queued')->index();
            $table->string('original_name')->nullable();
            $table->string('path');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_import_tasks');
    }
};
