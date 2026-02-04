<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_links', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('local_type', 100);
            $table->string('local_id', 64);
            $table->string('external_id', 100)->nullable();
            $table->text('psm_pin_encrypted')->nullable();
            $table->json('external_payload')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'local_type', 'local_id']);
            $table->index(['provider', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_links');
    }
};
