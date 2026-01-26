<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('personal_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('identity_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('language')->nullable();
            $table->enum('education_level', ['none', 'primary', 'secondary', 'university'])->nullable();
            $table->string('commune')->nullable();
            $table->boolean('cmu')->nullable()->default(false);
            $table->string('height')->nullable();
            $table->string('occupation')->nullable();
            $table->timestamps();

            $table->index('identity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_profiles');
    }
};