<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportsTable extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_session_id')->nullable()->constrained('evaluation_sessions')->nullOnDelete();
            $table->foreignId('decision_path_id')->nullable()->constrained('decision_paths')->nullOnDelete();
            $table->uuid('identity_id')->nullable();
            $table->string('report_code')->nullable();
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->json('answers')->nullable();
            $table->float('score')->nullable();
            $table->unsignedInteger('max_score')->nullable();
            $table->string('risk_level')->nullable();
            $table->json('specialties')->nullable();
            $table->text('recommendation')->nullable();
            $table->timestamps();

            $table->index('identity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
}