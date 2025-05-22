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
        Schema::create('capsule_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('capsule_id')->constrained()->onDelete('cascade');
            $table->unsignedTinyInteger('position')->default(1);

            $table->enum('type', [
                'text', 'image', 'video', 'audio', 'mixed', 'quiz'
            ])->default('text');

            $table->string('bg_color')->nullable();            // Exemple : "#FFDD00"
            $table->text('content_text')->nullable();          // Texte principal
            $table->string('media_url')->nullable();           // Image, audio ou vidéo

            $table->string('cta_label')->nullable();           // Bouton : "Voir guide", "Prendre RDV"
            $table->string('cta_link')->nullable();            // Lien vers action

            $table->boolean('sponsored_content')->default(false);
            $table->text('sponsor_message')->nullable();       // Message sponsor discret
            $table->enum('sponsored_link_type', [
                'product', 'event', 'location', 'call'
            ])->nullable();
            $table->string('sponsored_action_value')->nullable();

            $table->unsignedInteger('duration_seconds')->default(7);

            $table->json('audience_tags')->nullable();         // Ciblage : ["adolescente", "menopause"]
            $table->enum('risk_level', ['low', 'moderate', 'high'])->nullable();

            $table->json('quiz_data')->nullable();             // Quiz interactif

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capsule_items');
    }
};
