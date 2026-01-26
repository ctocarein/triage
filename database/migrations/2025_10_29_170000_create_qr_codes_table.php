<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->enum('type', ['portal', 'campaign', 'evaluation', 'anonymous'])->default('evaluation');
            $table->string('code', 50)->unique();
            $table->string('url')->unique();
            $table->foreignId('campaign_id')
                ->nullable()
                ->constrained('campaigns')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->enum('status', ['active', 'used', 'expired', 'revoked'])->default('active');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};