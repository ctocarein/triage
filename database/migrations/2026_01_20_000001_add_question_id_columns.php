<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_answers', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_answers', 'question_id')) {
                $table->foreignId('question_id')
                    ->nullable()
                    ->after('evaluation_session_id')
                    ->constrained('dynamic_question_templates')
                    ->nullOnDelete();
                $table->index('question_id');
            }
        });

        Schema::table('decision_nodes', function (Blueprint $table) {
            if (!Schema::hasColumn('decision_nodes', 'question_id')) {
                $table->foreignId('question_id')
                    ->nullable()
                    ->after('decision_path_id')
                    ->constrained('dynamic_question_templates')
                    ->nullOnDelete();
                $table->index('question_id');
            }
        });

        Schema::table('user_reminders', function (Blueprint $table) {
            if (!Schema::hasColumn('user_reminders', 'question_id')) {
                $table->foreignId('question_id')
                    ->nullable()
                    ->after('identity_id')
                    ->constrained('dynamic_question_templates')
                    ->nullOnDelete();
                $table->index('question_id');
            }
        });

        if (Schema::hasColumn('evaluation_answers', 'dynamic_question_template_id')) {
            DB::table('evaluation_answers')
                ->whereNull('question_id')
                ->update(['question_id' => DB::raw('dynamic_question_template_id')]);
        }

        if (Schema::hasColumn('decision_nodes', 'dynamic_question_template_id')) {
            DB::table('decision_nodes')
                ->whereNull('question_id')
                ->update(['question_id' => DB::raw('dynamic_question_template_id')]);
        }

        if (Schema::hasColumn('user_reminders', 'dynamic_question_template_id')) {
            DB::table('user_reminders')
                ->whereNull('question_id')
                ->update(['question_id' => DB::raw('dynamic_question_template_id')]);
        }
    }

    public function down(): void
    {
        Schema::table('evaluation_answers', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_answers', 'question_id')) {
                $table->dropConstrainedForeignId('question_id');
                $table->dropIndex(['question_id']);
            }
        });

        Schema::table('decision_nodes', function (Blueprint $table) {
            if (Schema::hasColumn('decision_nodes', 'question_id')) {
                $table->dropConstrainedForeignId('question_id');
                $table->dropIndex(['question_id']);
            }
        });

        Schema::table('user_reminders', function (Blueprint $table) {
            if (Schema::hasColumn('user_reminders', 'question_id')) {
                $table->dropConstrainedForeignId('question_id');
                $table->dropIndex(['question_id']);
            }
        });
    }
};
