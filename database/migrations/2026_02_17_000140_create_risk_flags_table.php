<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_model_id')->constrained('risk_models')->cascadeOnDelete();
            $table->foreignId('model_version_id')->constrained('model_versions')->cascadeOnDelete();
            $table->string('fact_key');
            $table->enum('type', ['emergency', 'urgent']);
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['fact_key']);
            $table->index(['type']);
            $table->index(['is_active']);
            $table->unique(['risk_model_id', 'model_version_id', 'fact_key', 'type']);

            $table->foreign('fact_key')->references('key')->on('facts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_flags');
    }
};