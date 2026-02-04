<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE dynamic_question_templates MODIFY answer_target ENUM('personal_profile','medical_profile','evaluation','followup','tracking','survey','greeting','custom','consent') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE dynamic_question_templates MODIFY answer_target ENUM('personal_profile','medical_profile','evaluation','followup','tracking','survey','greeting','custom') NULL");
    }
};
