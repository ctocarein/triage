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
        $table->string('bloc_key')->nullable(); // Ex: douleur_sein, ecoulement, etc.

        // Min/Max pour les types 'scale' ou validation dynamique
        $table->integer('min')->nullable();
        $table->integer('max')->nullable();

        // Données complémentaires (profil requis, contexte, historique)
        $table->json('meta')->nullable(); // ex: {"source": "profile.medical", "tree_version": "sein_v1.0"}

        // Type de question
        $table->enum('type', [
            'boolean', 'text', 'number', 'date',
            'select_one', 'select_multiple',
            'info', 'file', 'hidden',
            'checkbox', 'radio',
            'slider', 'scale', 'textarea',
            'dynamic', 'custom',
            'map'
        ]);

       // Intelligence reminder / activation contextuelle
        $table->string('question_type')->default('question'); 
        $table->json('activation_condition')->nullable();     
        $table->string('period')->nullable();                 
        // Cible de la réponse (profil, suivi, etc.)
        // 'credential' : la reponse alimente les identifiants de connexion
        // (numero de telephone), qui ne vivent pas dans personal_profiles.
        // Les bases deja migrees le recoivent par 2026_06_26_000001.
        $table->enum('answer_target', [
            'personal_profile', 'medical_profile',
            'evaluation', 'followup', 'tracking', 'survey', 'greeting', 'custom', 'consent',
            'credential'
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

        // Indexation
        $table->index(['question_group_id'], 'idx_group');
        $table->index(['question_group_id', 'order'], 'idx_group_order');
        $table->index(['is_entry_point'], 'idx_entry_point');
        $table->index('answer_target', 'idx_answer_target');
        $table->index(['order'], 'idx_order');

        // Timestamps
        $table->timestamps();

    });

    }

    public function down()
    {
        Schema::dropIfExists('dynamic_question_templates');
    }
};
