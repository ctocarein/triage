<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('medical_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('identity_id');

            $table->boolean('is_pregnant')->default(false);
            $table->boolean('breastfeeding')->default(false);
            $table->enum('menopause_status', ['unknown', 'pre', 'post'])->default('unknown');

            $table->boolean('family_cancer_history')->default(false);
            $table->string('screening_type')->nullable();
            $table->boolean('has_breast_cancer')->default(false);
            $table->boolean('has_ovarian_cancer')->default(false);
            $table->boolean('has_cervical_cancer')->default(false);
            $table->boolean('has_other_cancer')->default(false);

            $table->integer('first_sexual_intercourse_age')->nullable();
            $table->integer('number_of_partners')->nullable();
            $table->integer('number_of_pregnancies')->nullable();
            $table->integer('number_of_abortions')->nullable();
            $table->integer('number_of_children')->nullable();

            $table->date('last_self_exam_date')->nullable();
            $table->date('last_menstruation_date')->nullable();
            $table->unsignedTinyInteger('fixed_self_exam_day')->nullable();
            $table->date('last_screening_date')->nullable();
            $table->date('last_cervical_screening_date')->nullable();

            $table->string('cmu_number')->nullable();
            $table->float('height')->nullable();
            $table->float('weight')->nullable();
            $table->string('blood_type')->nullable();
            $table->string('allergies')->nullable();

            $table->boolean('exposed_to_smoke')->default(false);
            $table->string('exposure_type')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('identity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_profiles');
    }
};