<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index();
            $table->string('title');
            $table->string('command');
            $table->json('options')->nullable();
            $table->string('status')->default('queued')->index();
            $table->integer('exit_code')->nullable();
            $table->longText('log')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_output_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_tasks');
    }
};
