<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_session_id')->nullable()->constrained('evaluation_sessions')->nullOnDelete();
            $table->foreignId('decision_path_id')->nullable()->constrained('decision_paths')->nullOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('report_code')->nullable();      // ex: RPT-2025-00123
            $table->string('title')->nullable();          // titre du rapport
            $table->text('summary')->nullable();            // synthèse globale
            $table->json('answers')->nullable();            // toutes les réponses, clé:valeur
            $table->float('score')->nullable();
            $table->unsignedInteger('max_score')->nullable();
            $table->string('risk_level')->nullable();       // "low", "moderate", "high"
            $table->json('specialties')->nullable();
            $table->text('recommendation')->nullable();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
}
