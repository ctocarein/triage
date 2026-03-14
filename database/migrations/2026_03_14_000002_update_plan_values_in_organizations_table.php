<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate old plan values to new ones
        DB::table('organizations')
            ->whereIn('plan', ['FreeMin', 'FreePlus'])
            ->update(['plan' => 'Freemium']);

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('plan')->default('Freemium')->change();
        });
    }

    public function down(): void
    {
        DB::table('organizations')
            ->where('plan', 'Freemium')
            ->update(['plan' => 'FreeMin']);

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('plan')->default('FreeMin')->change();
        });
    }
};
