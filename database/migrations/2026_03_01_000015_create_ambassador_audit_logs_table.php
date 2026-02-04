<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ambassador_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ambassador_id');
            $table->string('action');
            $table->json('payload');
            $table->timestamp('created_at');

            $table->foreign('ambassador_id')->references('id')->on('ambassadors');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ambassador_audit_logs');
    }
};
