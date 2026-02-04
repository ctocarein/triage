<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('professional_profiles', function (Blueprint $table) {
            $table->id();
            $table->uuid('identity_id');
            $table->uuid('organization_id')->nullable();
            $table->string('full_name');
            $table->string('job_title')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();

            $table->index('identity_id');
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professional_profiles');
    }
};
