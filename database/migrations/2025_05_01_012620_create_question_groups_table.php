<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('question_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();         // Clé d'identification logique
            $table->string('label');                // Nom affiché
            $table->string('icon')->nullable();     // Pour l'affichage frontend
            $table->string('color')->nullable();    // Pour UI
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(0);     // Ordre dans le flow
            $table->boolean('is_active')->default(true);      // Pour activer/désactiver un groupe
            $table->json('conditions')->nullable();           // Conditions spécifiques d'affichage

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('question_groups');
    }
};