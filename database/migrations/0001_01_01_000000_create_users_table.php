<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // 🧑‍⚕️ Informations personnelles
            $table->string('firstname');
            $table->string('lastname');
            $table->string('number')->unique(); // Numéro de téléphone pro
            $table->string('otp')->nullable();  // OTP temporaire si besoin
            $table->string('password')->nullable(); // Pour accès backoffice

            // 🎓 Compétence et localisation
            $table->string('skill')->nullable(); // sage-femme, superviseur, etc.
            $table->string('place')->nullable(); // ville, région, etc.

            // 🪪 Identité
            $table->string('cni')->nullable();
            $table->string('cni_file')->nullable(); // URL ou path

            // 🔐 Statut
            $table->enum('status', ['active', 'pending', 'suspended', 'rejected'])->default('pending');

            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
