<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medical_profiles', function (Blueprint $table) {
            $table->id();

            // 🔗 Lien avec le compte
            $table->foreignId('account_id')->constrained()->onDelete('cascade');

            // 👩‍⚕️ Vie reproductive & hormonale
            $table->boolean('is_pregnant')->default(false);
            $table->boolean('breastfeeding')->default(false);
            $table->enum('menopause_status', ['unknown', 'pre', 'post'])->default('unknown');

            // 🧬 Historique médical
            $table->boolean('family_cancer_history')->default(false);
            $table->date('last_screening_date')->nullable();
            $table->string('screening_type')->nullable();

            // 🩺 Vie sexuelle & gynécologique
            $table->integer('first_sexual_intercourse_age')->nullable();
            $table->integer('number_of_partners')->nullable();
            $table->integer('number_of_pregnancies')->nullable();
            $table->integer('number_of_abortions')->nullable();
            $table->integer('number_of_children')->nullable();

            // 🌫️ Exposition environnementale
            $table->boolean('exposed_to_smoke')->default(false);
            $table->string('exposure_type')->nullable();

            // 📝 Observation libre (SF)
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_profiles');
    }
};
