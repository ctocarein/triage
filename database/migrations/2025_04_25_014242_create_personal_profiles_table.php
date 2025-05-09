<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('personal_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('language')->nullable();
            $table->enum('education_level', ['none', 'primary', 'secondary', 'university'])->nullable();
            $table->string('commune')->nullable();
            $table->string('national_id_number')->nullable();
            $table->string('height')->nullable(); // 🔁 taille ajoutée
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_profiles');
    }
};
