<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * `communes` -> `municipalities`.
 *
 * The table holds a static reference list, so the rename costs one statement
 * today. It stops being cheap as soon as `neighborhoods` hangs off it with a
 * `commune_id` foreign key -- a French column inside an English table is the
 * mix we are getting rid of.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('communes', 'municipalities');
    }

    public function down(): void
    {
        Schema::rename('municipalities', 'communes');
    }
};
