<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The national health map: regions and districts.
 *
 * This is the unit where care actually exists. An ambassador is a midwife in a
 * facility that belongs to a health district; routing a woman means pointing
 * her at a facility in her district. A neighbourhood name says nothing about
 * that, which is why the health map -- and not the municipality list --
 * becomes the routing key.
 *
 * Source: Carte sanitaire nationale, MSHPCMU (DIS 2023, 2025 edition).
 * 33 regions, 113 districts, 10 PRES.
 *
 * Two deliberate choices:
 *
 *  - `region_assignment_verified` defaults to FALSE on every district. The
 *    source states plainly that district -> region assignment was rebuilt from
 *    the administrative map and is not an official table, to be validated by
 *    the DIIS before production use. Encoding that as data rather than a
 *    comment means the back office can surface what still needs validating,
 *    and nobody inherits the caveat without seeing it.
 *
 *  - Coordinates are created empty. The source carries none, but the national
 *    map with markers needs them; adding the columns now costs nothing, adding
 *    them once districts are referenced everywhere costs a migration on live
 *    data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_regions', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('slug')->unique();
            $table->string('name');

            // Chef-lieu of the region.
            $table->string('seat')->nullable();

            // Pole regional d'excellence sante -- a grouping above regions.
            // A plain column while it carries no attributes of its own; it can
            // become a table the day it does.
            $table->string('pres')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();
        });

        Schema::create('health_districts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('health_region_id')->constrained('health_regions');
            $table->string('code')->unique();
            $table->string('slug')->unique();
            $table->string('name');

            // Chef-lieu of the district. Absent from the source, which only
            // carries it at region level: for most districts the name is the
            // town, but not for composite ones such as Adjame-Plateau-Attecoube.
            $table->string('seat')->nullable();

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->boolean('region_assignment_verified')->default(false);

            // Kept verbatim from the source when it flags an ambiguity, e.g.
            // Nassian, placed under Bounkani here and under Gontougo elsewhere.
            $table->text('assignment_note')->nullable();

            $table->timestamps();

            $table->index('health_region_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_districts');
        Schema::dropIfExists('health_regions');
    }
};
