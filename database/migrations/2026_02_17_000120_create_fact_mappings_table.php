<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fact_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('question_key');
            $table->json('answer_value')->nullable();
            $table->string('operator')->nullable();
            $table->string('fact_key');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['question_key']);
            $table->index(['fact_key']);
            $table->index(['is_active']);

            $table->foreign('fact_key')->references('key')->on('facts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fact_mappings');
    }
};