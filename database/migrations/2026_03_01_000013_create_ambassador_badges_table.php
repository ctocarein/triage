<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambassador_badges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ambassador_id');
            $table->string('badge_code');
            $table->enum('status', ['earned', 'revoked']);
            $table->timestamp('earned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->json('evidence');
            $table->timestamps();

            $table->unique(['ambassador_id', 'badge_code']);
            $table->foreign('ambassador_id')->references('id')->on('ambassadors');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambassador_badges');
    }
};
