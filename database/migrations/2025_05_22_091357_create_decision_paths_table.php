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
            $table->string('key')->unique();                // ex: 'dp_breast_pain'
            $table->string('title');                        // Titre lisible pour l’UI
            $table->text('description')->nullable();        // Description longue
            $table->string('category')->nullable();         // ex: 'thematic', 'complete', 'symptome'
            $table->string('color')->nullable();            // ex: '#FF6699' (pour UI)
            $table->string('icon')->nullable();             // ex: 'breast', 'uterus', icône front
            $table->string('image_url')->nullable();        // Pour l’illustration front si besoin
            $table->unsignedInteger('order')->default(0);   // Pour trier les parcours
            $table->json('tree')->nullable();               // Optionnel: pour cache/export du flow
            $table->string('source_guideline')->nullable(); // Ex: 'OMS, HAS, INCa 2024'
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
