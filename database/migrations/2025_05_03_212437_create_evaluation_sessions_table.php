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
        Schema::create('evaluation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')
                  ->constrained()
                  ->cascadeOnDelete();
                  
            $table->foreignId('current_question_id')
                  ->nullable()
                  ->constrained('dynamic_question_templates')
                  ->nullOnDelete();
                  
            $table->enum('status', ['in_progress', 'completed', 'cancelled'])
                  ->default('in_progress');
                  
            $table->string('context')->nullable(); // "col_de_l_uterus", "sein", etc.
            $table->unsignedInteger('score')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Index
            $table->index('account_id');
            $table->index('status');
            $table->index('current_question_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_sessions');
    }
};