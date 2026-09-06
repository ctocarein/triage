<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('evaluation_answers')
            ->select('evaluation_session_id', 'dynamic_question_template_id')
            ->whereNotNull('dynamic_question_template_id')
            ->groupBy('evaluation_session_id', 'dynamic_question_template_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate) {
            throw new RuntimeException(
                'Cannot add unique index: duplicate evaluation answers exist for at least one session/question pair.'
            );
        }

        Schema::table('evaluation_answers', function (Blueprint $table): void {
            $table->unique(
                ['evaluation_session_id', 'dynamic_question_template_id'],
                'evaluation_answers_session_question_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_answers', function (Blueprint $table): void {
            $table->dropUnique('evaluation_answers_session_question_unique');
        });
    }
};
