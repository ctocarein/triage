<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $startYear = (int) date('Y') - 90;
        $endYear = (int) date('Y');

        DB::table('dynamic_question_templates')
            ->where('key', 'consent_declined_year')
            ->update([
                'options' => json_encode(
                    array_map(
                        fn ($year) => ['value' => (string) $year, 'label' => (string) $year],
                        range($endYear, $startYear)
                    ),
                    JSON_UNESCAPED_UNICODE
                ),
            ]);
    }

    public function down(): void
    {
        $startYear = (int) date('Y') - 90;
        $endYear = (int) date('Y') - 18;

        DB::table('dynamic_question_templates')
            ->where('key', 'consent_declined_year')
            ->update([
                'options' => json_encode(
                    array_map(
                        fn ($year) => ['value' => (string) $year, 'label' => (string) $year],
                        range($endYear, $startYear)
                    ),
                    JSON_UNESCAPED_UNICODE
                ),
            ]);
    }
};
