<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Migrations\Migration as BaseMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le score doit stocker des valeurs décimales (risk_weight peut être un float).
     * Un cast integer tronquait 9.7 à 9, laissant un patient en zone "low" au lieu de "moderate".
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite n'a pas UNSIGNED DECIMAL — la colonne existante accepte déjà les floats
            return;
        }

        Schema::table('evaluation_sessions', function (Blueprint $table) {
            $table->decimal('score', 8, 2)->default(0)->unsigned()->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('evaluation_sessions', function (Blueprint $table) {
            $table->unsignedInteger('score')->default(0)->change();
        });
    }
};
