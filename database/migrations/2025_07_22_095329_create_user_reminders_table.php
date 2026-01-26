<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_reminders', function (Blueprint $table) {
            $table->id();
            $table->uuid('identity_id');
            $table->foreignId('dynamic_question_template_id')->constrained();
            $table->date('reminded_at');
            $table->date('completed_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index('identity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reminders');
    }
};