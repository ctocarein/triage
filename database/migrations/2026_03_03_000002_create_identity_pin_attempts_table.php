<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_pin_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('identity_id');
            $table->uuid('device_id')->nullable();
            $table->boolean('success');
            $table->timestamps();

            $table->index(['identity_id', 'created_at']);
            $table->foreign('identity_id')->references('id')->on('identities')->cascadeOnDelete();
            $table->foreign('device_id')->references('id')->on('identity_devices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_pin_attempts');
    }
};
