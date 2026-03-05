<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // MySQL/MariaDB : contrainte ENUM ; SQLite n'a pas d'ENUM (les colonnes string acceptent tout).
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE dynamic_question_templates MODIFY answer_target ENUM('personal_profile','medical_profile','evaluation','followup','tracking','survey','greeting','custom','consent') NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE dynamic_question_templates MODIFY answer_target ENUM('personal_profile','medical_profile','evaluation','followup','tracking','survey','greeting','custom') NULL");
        }
    }
};
