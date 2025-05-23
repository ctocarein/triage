<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
                $table->foreignId('account_id')->constrained()->onDelete('cascade');
    $table->foreignId('evaluation_session_id')->constrained()->onDelete('cascade');

    $table->integer('score')->default(0);
    $table->enum('status', ['completed', 'reviewed', 'escalated'])->default('completed');
    $table->string('summary'); // Exemple : "Pas de signe préoccupant"
    $table->text('recommendation_text')->nullable();
    $table->enum('recommended_action', ['wait', 'visit_clinic', 'schedule_exam'])->nullable();
    $table->enum('risk_level', ['low', 'moderate', 'high'])->default('low');
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
};
