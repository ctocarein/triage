<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
    Schema::create('accounts', function (Blueprint $table) {
    $table->id();

    // 🔐 Authentification
    $table->string('phone')->unique();
    $table->string('pin_code')->nullable(); // hashed
    $table->string('phone_uuid')->nullable();

    // 🧭 Statuts & rôles
    $table->enum('type', ['standard', 'subscriber','midwife', 'ambassador'])
          ->default('standard'); 
    $table->boolean('is_anonymous')->default(false);
    $table->timestamp('is_anonymous_at')->nullable();
    $table->enum('status', ['active', 'inactive', 'blocked'])
          ->default('active');
    $table->boolean('is_active')->default(true);
    $table->boolean('needs_assistance')->default(false);
    // 🔗 Liens logiques
    $table->foreignId('health_facility_id')->nullable();

    $table->foreignId('user_id')->nullable() // admin, superviseur, partenaire
          ->constrained()
          ->nullOnDelete();

    $table->foreignId('created_by')->nullable() // si la SF a créé ce compte
          ->constrained('accounts')
          ->nullOnDelete();

    // ⏱️ Suivi
    $table->timestamp('last_login_at')->nullable();

    // Timestamps Laravel
    $table->timestamps();
});

    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
