<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the beneficiary is attached in the health system.
 *
 * `municipality` and this column answer two different questions and neither
 * replaces the other:
 *
 *   municipality        what she says -- her address, used to find her again
 *   health_district_id  where she is cared for -- the routing key
 *
 * A woman does not know her health district; she knows her neighbourhood. The
 * district is therefore declared by whoever conducts the evaluation -- a health
 * professional who does know -- which is only possible because evaluations
 * stopped being self-administered.
 *
 * Nullable: sessions predating the health map have no district, and inferring
 * one from a municipality name is exactly the guess that has to be avoided.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('personal_profiles', function (Blueprint $table): void {
            $table->foreignId('health_district_id')
                ->nullable()
                ->after('municipality')
                ->constrained('health_districts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personal_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('health_district_id');
        });
    }
};
