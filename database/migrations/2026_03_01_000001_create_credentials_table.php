<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credentials', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('identity_id');
            $table->enum('type', ['email', 'phone']);
            $table->string('identifier');
            $table->string('secret_hash')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['type', 'identifier']);
            $table->index(['identity_id', 'type']);
            $table->foreign('identity_id')->references('id')->on('identities')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};
