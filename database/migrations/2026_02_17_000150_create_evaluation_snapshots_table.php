<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_session_id')->constrained('evaluation_sessions')->cascadeOnDelete();
            $table->json('facts')->nullable();
            $table->json('risk')->nullable();
            $table->json('flags')->nullable();
            $table->decimal('confidence_score', 6, 4)->nullable();
            $table->string('snapshot_hash', 128)->nullable();
            $table->timestamps();

            $table->index(['evaluation_session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_snapshots');
    }
};