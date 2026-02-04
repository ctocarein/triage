<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_security_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('identity_id');
            $table->string('type');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['identity_id', 'created_at']);
            $table->foreign('identity_id')->references('id')->on('identities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_security_events');
    }
};
