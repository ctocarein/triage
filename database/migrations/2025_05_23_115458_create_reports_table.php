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
            $table->foreignId('evaluation_session_id')->constrained()->onDelete('cascade');

            $table->unsignedInteger('score')->default(0);
            $table->enum('risk_level', ['low', 'moderate', 'high'])->default('low');
            $table->text('recommendation')->nullable();
            $table->json('summary')->nullable();

            $table->foreignId('decision_path_id')->nullable()->constrained()->nullOnDelete();

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
