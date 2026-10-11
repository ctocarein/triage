<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les etablissements vers lesquels orienter une femme apres son evaluation.
 *
 * `has_mammography` est l'information qui decide de l'orientation : une femme
 * a risque doit savoir ou passer une mammographie, pas seulement ou consulter.
 *
 * `is_sample` marque les lignes d'exemple posees en attendant le referentiel
 * reel : l'application les signale, elles ne doivent pas passer pour vraies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_facilities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            // chu, chr, hospital, clinic, health_center... -- libre tant que le
            // referentiel n'est pas fixe.
            $table->string('kind')->nullable();
            $table->foreignId('health_district_id')
                ->nullable()
                ->constrained('health_districts')
                ->nullOnDelete();
            $table->string('municipality')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('has_mammography')->default(false);
            $table->boolean('is_sample')->default(false);
            $table->timestamps();

            $table->index('has_mammography');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_facilities');
    }
};
