<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('memberships');

        Schema::create('memberships', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('identity_id');
            $table->uuid('organization_id')->nullable();
            // 'Ambassador' figure ici pour que les bases creees de zero (SQLite en test)
            // connaissent le role : la migration 2026_03_02_000000 qui l'ajoute est
            // reservee a MySQL/MariaDB, donc elle ne le fournissait qu'en production.
            // Les bases MySQL deja migrees l'ont recu par cette migration-la.
            $table->enum('role', [
                'Beneficiary',
                'FieldAgent',
                'Ambassador',
                'Practitioner',
                'OrgManager',
                'PlatformAdmin',
                'System',
            ]);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index(['identity_id', 'role']);
            $table->index(['organization_id', 'role']);
            $table->unique(['identity_id', 'organization_id', 'role']);
            $table->foreign('identity_id')->references('id')->on('identities')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
