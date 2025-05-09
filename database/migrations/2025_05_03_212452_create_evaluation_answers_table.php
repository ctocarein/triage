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
        Schema::create('evaluation_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dynamic_question_template_id')->constrained()->cascadeOnDelete();
            $table->json('value')->nullable(); // JSON (texte, booléen, choix multiple...)
            $table->boolean('is_skipped')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_answers');
    }
};
