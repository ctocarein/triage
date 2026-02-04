<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambassador_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ambassador_id')->unique();
            $table->string('level_code');
            $table->timestamp('computed_at');
            $table->timestamps();

            $table->foreign('ambassador_id')->references('id')->on('ambassadors');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambassador_levels');
    }
};
