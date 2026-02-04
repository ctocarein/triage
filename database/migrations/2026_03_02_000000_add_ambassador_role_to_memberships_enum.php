<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE memberships MODIFY role ENUM('Beneficiary','FieldAgent','Ambassador','Practitioner','OrgManager','PlatformAdmin','System') NOT NULL"
            );
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement(
                "ALTER TABLE memberships MODIFY role ENUM('Beneficiary','FieldAgent','Practitioner','OrgManager','PlatformAdmin','System') NOT NULL"
            );
        }
    }
};
