<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `personal_profiles.commune` -> `municipality`, and the catalogue key with it.
 *
 * ProfileUpdaterService writes `$personalData[$question->key] = $value`: the
 * question key IS the profile column name. Renaming one without the other
 * silently stops persisting the answer -- no error, just a column that never
 * fills again. The two have to move together, which is why a catalogue update
 * lives in a schema migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_profiles', function (Blueprint $table): void {
            $table->renameColumn('commune', 'municipality');
        });

        DB::table('dynamic_question_templates')
            ->where('key', 'commune')
            ->update(['key' => 'municipality']);
    }

    public function down(): void
    {
        Schema::table('personal_profiles', function (Blueprint $table): void {
            $table->renameColumn('municipality', 'commune');
        });

        DB::table('dynamic_question_templates')
            ->where('key', 'municipality')
            ->update(['key' => 'commune']);
    }
};
