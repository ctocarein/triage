<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // identities : email_unverified → pending_validation → active | rejected | suspended | anonymized
            DB::statement(
                "ALTER TABLE identities MODIFY status ENUM(
                    'email_unverified',
                    'pending_validation',
                    'active',
                    'rejected',
                    'suspended',
                    'anonymized'
                ) NOT NULL DEFAULT 'active'"
            );

            // memberships : pending → active | rejected | inactive
            DB::statement(
                "ALTER TABLE memberships MODIFY status ENUM(
                    'pending',
                    'active',
                    'rejected',
                    'inactive'
                ) NOT NULL DEFAULT 'active'"
            );
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE identities MODIFY status ENUM('active','suspended','anonymized') NOT NULL DEFAULT 'active'"
            );

            DB::statement(
                "ALTER TABLE memberships MODIFY status ENUM('active','inactive') NOT NULL DEFAULT 'active'"
            );
        }
    }
};
