<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambassador_referral_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('referral_id');
            $table->enum('event_type', ['started', 'completed']);
            $table->string('source')->nullable();
            $table->timestamp('occurred_at');

            $table->foreign('referral_id')->references('id')->on('ambassador_referrals');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambassador_referral_usages');
    }
};
