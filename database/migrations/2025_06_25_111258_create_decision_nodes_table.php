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
        Schema::create('decision_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decision_path_id')->constrained()->onDelete('cascade');
            $table->string('node_key');                     // ex: node_01
            $table->foreignId('dynamic_question_template_id')->nullable()->constrained('dynamic_question_templates')->nullOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->json('branches')->nullable();           // logique de branchement (ex: { "yes": "node_02", "no": "node_03" })
            $table->float('risk_weight')->default(0);       // poids pour le score global
            $table->string('node_type')->default('question'); // "question", "action", "end"
            $table->text('outcome_message')->nullable();    // Message de recommandation/contextuel
            $table->json('specialties')->nullable();        // ex: ["gynécologie", "généraliste"]
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decision_nodes');
    }
};
