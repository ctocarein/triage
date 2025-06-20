<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('thematic_blocs', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Clé technique (ex: 'symptom_breast_pain')
            $table->string('label');         // Titre affiché
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
            $table->foreignId('question_group_id')
                  ->constrained('question_groups')
                  ->onDelete('cascade'); // Liaison forte
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order')->default(0); // Pour trier les blocs dans l’UI
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('thematic_blocs');
    }
};
