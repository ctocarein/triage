<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('accounts');
        Schema::dropIfExists('users');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('tenants');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Legacy tables intentionally not restored.
    }
};
