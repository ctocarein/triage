<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('organization_id');
            $table->string('name');
            $table->enum('objective', ['sensibilisation', 'depistage', 'suivi_personnel', 'rse', 'medical']);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'ongoing', 'completed', 'archived'])->default('draft');
            $table->uuid('created_by');
            $table->uuid('validated_by')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['objective']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};