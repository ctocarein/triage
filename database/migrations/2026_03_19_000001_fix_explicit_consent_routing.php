<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('dynamic_question_templates')
            ->where('key', 'explicit_consent')
            ->update([
                'type' => 'select_one',
                'answer_target' => 'consent',
                'routing_rules' => json_encode([
                    [
                        'if' => ['source' => 'answer', 'operator' => '=', 'value' => 'no'],
                        'next' => 'question:consent_declined_thank_you',
                    ],
                    [
                        'if' => ['source' => 'answer', 'operator' => '=', 'value' => 'yes'],
                        'next' => 'question:raison_visite',
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ]);
    }

    public function down(): void
    {
        DB::table('dynamic_question_templates')
            ->where('key', 'explicit_consent')
            ->update([
                'type' => 'checkbox',
                'routing_rules' => json_encode([
                    [
                        'if' => ['source' => 'answer', 'operator' => '=', 'value' => 'no'],
                        'next' => 'queue:consent_declined_thank_you',
                    ],
                    [
                        'if' => ['source' => 'answer', 'operator' => '=', 'value' => 'yes'],
                        'next' => 'question:raison_visite',
                    ],
                ], JSON_UNESCAPED_UNICODE),
            ]);
    }
};
