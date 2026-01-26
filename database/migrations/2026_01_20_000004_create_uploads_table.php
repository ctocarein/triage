<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('file_id')->unique();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->uuid('identity_id')->nullable();
            $table->timestamps();

            $table->index('identity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};