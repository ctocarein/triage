<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thematic_blocs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
            $table->text('description')->nullable();
            $table->json('visible_if')->nullable()
                ->comment('Conditions JSON évaluées pour déterminer si le bloc entier est visible. Vide = toujours visible.');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->foreignId('question_group_id')->nullable()->constrained('question_groups')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thematic_blocs');
    }
};
