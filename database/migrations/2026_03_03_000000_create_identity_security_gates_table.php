<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_security_gates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('identity_id')->unique();
            $table->enum('state', ['entry', 'limited', 'full', 'locked'])->default('entry');
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('panic_locked_at')->nullable();
            $table->string('last_unlock_method')->nullable();
            $table->timestamps();

            $table->foreign('identity_id')->references('id')->on('identities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_security_gates');
    }
};
