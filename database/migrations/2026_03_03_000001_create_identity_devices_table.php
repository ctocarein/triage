<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('identity_id');
            $table->string('device_fingerprint');
            $table->timestamp('trusted_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['identity_id', 'device_fingerprint']);
            $table->index('device_fingerprint');
            $table->foreign('identity_id')->references('id')->on('identities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_devices');
    }
};
