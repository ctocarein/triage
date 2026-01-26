<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('qr_code_id')
                ->constrained('qr_codes')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->uuid('identity_id')->nullable();
            $table->timestamp('scanned_at')->useCurrent();
            $table->json('location')->nullable();
            $table->string('device_info')->nullable();
            $table->string('ip_address')->nullable();
            $table->foreignId('evaluation_session_id')
                ->nullable()
                ->constrained('evaluation_sessions')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->enum('result', ['success', 'invalid', 'expired'])->default('success');
            $table->timestamps();

            $table->index(['qr_code_id', 'scanned_at']);
            $table->index(['identity_id', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_events');
    }
};