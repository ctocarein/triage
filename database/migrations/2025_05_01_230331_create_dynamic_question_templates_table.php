<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
    Schema::create('dynamic_question_templates', function (Blueprint $table) {
        $table->id();

        // Identification logique et affichage
        $table->string('key')->unique();
        $table->string('text');
        $table->string('label')->nullable(); // Peut être utile pour affichage frontend
        $table->string('placeholder')->nullable();

        // Type de question
        $table->enum('type', [
            'boolean', 'text', 'number', 'date',
            'select_one', 'select_multiple',
            'info', 'file', 'hidden',
            'checkbox', 'radio', 'textarea',
        ]);

        // Cible de la réponse (profil, suivi, etc.)
        $table->enum('answer_target', [
            'personal_profile', 'medical_profile',
            'evaluation', 'followup', 'tracking', 'survey'
        ])->nullable();

        // Groupe de questions
        $table->foreignId('question_group_id')
            ->nullable()
            ->constrained('question_groups')
            ->nullOnDelete();

        // Média associé
        $table->enum('media_type', ['image', 'video', 'audio'])->nullable();
        $table->text('media_url')->nullable();

        // Logique de réponse
        $table->json('options')->nullable();        // Pour select, checkbox, etc.
        $table->json('visible_if')->nullable();     // Conditions dynamiques
        $table->boolean('is_required')->default(false);
        $table->boolean('is_entry_point')->default(false);
        $table->unsignedInteger('order')->default(0);
        $table->unsignedInteger('risk_weight')->default(0);

        // Timestamps
        $table->timestamps();

        // Indexation
        $table->index(['question_group_id'], 'idx_group');
        $table->index(['question_group_id', 'order'], 'idx_group_order');
        $table->index(['is_entry_point'], 'idx_entry_point');
        $table->index('answer_target', 'idx_answer_target');
        $table->index(['order'], 'idx_order');
    });

    }

    public function down()
    {
        Schema::dropIfExists('dynamic_question_templates');
    }
};