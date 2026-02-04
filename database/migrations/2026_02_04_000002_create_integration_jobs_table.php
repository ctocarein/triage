<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('job_type', 100);
            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'skipped'])->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('payload')->nullable();
            $table->json('response')->nullable();
            $table->unsignedBigInteger('evaluation_session_id')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status']);
            $table->index(['job_type', 'status']);
            $table->index(['evaluation_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_jobs');
    }
};
