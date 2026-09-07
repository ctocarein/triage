<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attach facilities to the health map.
 *
 * `organizations` carried `country`, `city` and `address` as free text and
 * nothing else: there was no way to answer "which ambassador can receive this
 * woman". A district link makes that a lookup rather than a guess.
 *
 * Coordinates land here too, and this is where the map's markers actually come
 * from. A marker at a district's seat would claim that the women counted there
 * live in that town, when they live across the whole district -- the same
 * mistake that made the previous provider's map unreadable. A facility, by
 * contrast, really is at one point.
 *
 * Nullable, because the existing rows predate the health map and no truthful
 * value can be invented for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->foreignId('health_district_id')
                ->nullable()
                ->after('address')
                ->constrained('health_districts')
                ->nullOnDelete();

            $table->decimal('latitude', 10, 7)->nullable()->after('health_district_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('health_district_id');
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
