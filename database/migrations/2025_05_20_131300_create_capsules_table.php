<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
Schema::create('capsules', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->unsignedTinyInteger('target_age_min')->nullable();
            $table->unsignedTinyInteger('target_age_max')->nullable();
            $table->enum('audience_profile', ['all', 'menopause', 'cycle_regular', 'pregnant', 'postpartum'])->default('all');
            $table->json('target_pathology')->nullable(); // ex: ["sein", "col"]
            $table->enum('target_stage', ['awareness', 'screening', 'treatment', 'followup'])->nullable();

            $table->string('language', 5)->default('fr');

            $table->string('sponsored_by')->nullable();
            $table->string('sponsor_logo_url')->nullable();
            $table->boolean('sponsor_priority')->default(false);

            $table->unsignedSmallInteger('max_displays')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('capsules');
    }
};
