<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('decision_paths', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();                // ex: dp_breast_pain
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();         // ex: "symptôme"
            $table->json('tree')->nullable();               // Arbre complet (optionnel, pour cache/export)
            $table->string('source_guideline')->nullable();
            $table->string('version')->default('1.0');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decision_paths');
    }
};
