<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The directory that maps what a woman says to where she is cared for.
 *
 * Nobody holds a neighbourhood -> health district gazetteer for the country,
 * and building one up front would take months and be wrong on delivery. But
 * every assisted evaluation produces one true pair: the place she named, and
 * the district a health professional selected. The directory is therefore not
 * a prerequisite of the work -- it is a by-product of it.
 *
 * `normalized_key` is the piece that makes it work. Without it "Zimbabwé",
 * "Zimbabwe", "ZIMBABWE" and "zimbabwe " are four entries, which is precisely
 * how the previous provider ended up with a residence chart of twenty
 * unusable slivers.
 *
 * The key is unique per district, not globally: the same name can exist in two
 * districts, and a global unique key would silently pick one of them. Several
 * candidates for one name means the name is ambiguous, and an ambiguous name
 * must produce no suggestion rather than a coin toss.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_aliases', function (Blueprint $table): void {
            $table->id();

            // Lowercased, unaccented, punctuation collapsed -- the form two
            // spellings of the same place have in common.
            $table->string('normalized_key');

            // The most frequently encountered spelling, for display.
            $table->string('raw_label');

            // Which question produced it. `municipality` today; a free-text
            // locality field would slot in here without a migration.
            $table->string('source')->default('municipality');

            $table->foreignId('health_district_id')->constrained('health_districts')->cascadeOnDelete();

            // How many completed evaluations confirmed this pair. Used to rank
            // candidates, never to decide alone.
            $table->unsignedInteger('occurrences')->default(0);

            // Set when a human vouched for the mapping. A confirmed pair wins
            // over frequency: someone who knows the terrain outranks a count.
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            $table->unique(['normalized_key', 'health_district_id']);
            $table->index('normalized_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_aliases');
    }
};
