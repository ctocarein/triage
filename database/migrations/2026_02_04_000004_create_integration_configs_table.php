<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integration_configs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('config_key', 100);
            $table->text('config_value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();

            $table->unique(['provider', 'config_key']);
            $table->index(['provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_configs');
    }
};
