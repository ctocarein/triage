<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambassador_referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ambassador_id');
            $table->string('code')->unique();
            $table->enum('channel', ['whatsapp', 'sms', 'qr', 'other']);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->foreign('ambassador_id')->references('id')->on('ambassadors');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambassador_referrals');
    }
};
