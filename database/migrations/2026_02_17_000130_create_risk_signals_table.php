<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_model_id')->constrained('risk_models')->cascadeOnDelete();
            $table->foreignId('model_version_id')->constrained('model_versions')->cascadeOnDelete();
            $table->string('fact_key');
            $table->integer('weight');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['fact_key']);
            $table->index(['is_active']);
            $table->unique(['risk_model_id', 'model_version_id', 'fact_key']);

            $table->foreign('fact_key')->references('key')->on('facts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_signals');
    }
};