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

            // Relations principales
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('evaluation_session_id')->constrained()->onDelete('cascade');
            $table->foreignId('decision_path_id')->nullable()->constrained()->onDelete('set null');

            // Données du rapport
            $table->integer('score')->default(0);
            $table->string('risk_level', 24);
            $table->text('recommendation'); // Pour JSON, voir plus bas
            $table->json('summary')->nullable();

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
