<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_referral_clicks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('referral_id');
            // Token unique généré au clic, transmis via Android Install Referrer ou presse-papiers
            $table->string('click_token', 64)->unique();
            // Empreinte pour le fallback : SHA-256(ip + '|' + device_model)
            $table->string('device_fingerprint', 64)->nullable()->index();
            $table->string('ip_address', 45);
            $table->string('device_model', 128)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('claimed_at')->nullable();
            $table->uuid('claimed_by_user_id')->nullable();
            $table->timestamps();

            $table->index('click_token');
            $table->index(['ip_address', 'claimed_at']);
            $table->foreign('referral_id')->references('id')->on('ambassador_referrals')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_referral_clicks');
    }
};
