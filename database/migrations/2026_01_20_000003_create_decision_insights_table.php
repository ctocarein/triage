<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decision_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_session_id')->constrained('evaluation_sessions')->cascadeOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->uuid('identity_id')->nullable();

            $table->string('model_version');
            $table->string('calibration_tag')->nullable();

            $table->decimal('risk_score', 6, 4);
            $table->string('risk_level');
            $table->float('score_total')->nullable();

            $table->decimal('confidence_score', 6, 4);
            $table->decimal('confidence_interval_low', 6, 4);
            $table->decimal('confidence_interval_high', 6, 4);

            $table->json('data_quality');
            $table->json('explanations')->nullable();

            $table->string('suggested_action');
            $table->boolean('requires_human_validation')->default(false);
            $table->string('human_validation_reason')->nullable();
            $table->json('final_decision')->nullable();

            $table->timestamps();

            $table->index(['evaluation_session_id', 'created_at']);
            $table->index('identity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decision_insights');
    }
};