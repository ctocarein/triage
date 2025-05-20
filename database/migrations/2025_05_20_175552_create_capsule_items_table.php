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

            $table->enum('type', ['text', 'image', 'video', 'audio', 'mixed'])->default('text');
            $table->text('content_text')->nullable();
            $table->string('media_url')->nullable();

            $table->string('cta_label')->nullable();      // ex: "En savoir +"
            $table->string('cta_link')->nullable();       // URL

            $table->boolean('sponsored_content')->default(false);
            $table->text('sponsor_message')->nullable();
            $table->enum('sponsored_link_type', ['product', 'event', 'location', 'call'])->nullable();
            $table->string('sponsored_action_value')->nullable();

            $table->unsignedInteger('duration_seconds')->default(7);

            $table->json('audience_tags')->nullable();    // ex: ["adolescente", "menopause"]
            $table->enum('risk_level', ['low', 'moderate', 'high'])->nullable();

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
