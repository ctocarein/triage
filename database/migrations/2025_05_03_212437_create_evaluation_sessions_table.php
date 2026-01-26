<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_sessions', function (Blueprint $table) {
            $table->id();

            $table->uuid('identity_id');
            $table->foreignId('current_question_id')
                  ->nullable()
                  ->constrained('dynamic_question_templates')
                  ->nullOnDelete();
            $table->enum('status', ['in_progress', 'completed', 'cancelled'])->default('in_progress');

            $table->string('type')->default('complete');
            $table->string('context')->nullable();
            $table->string('risk_level')->nullable();
            $table->unsignedInteger('score')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('is_completed')->default(false);

            $table->string('current_bloc')->nullable();
            $table->json('target_blocs')->nullable();
            $table->json('visited_blocs')->nullable();

            $table->timestamps();
            $table->index('identity_id');
            $table->index('status');
            $table->index('current_question_id');
            $table->unsignedBigInteger('decision_path_id')->nullable(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_sessions');
    }
};