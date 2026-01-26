<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('evaluation_sessions', 'current_node_key')) {
                $table->string('current_node_key')->nullable()->after('current_question_id');
            }
            if (!Schema::hasColumn('evaluation_sessions', 'decision_path_key')) {
                $table->string('decision_path_key')->nullable()->after('decision_path_id');
            }
            if (!Schema::hasColumn('evaluation_sessions', 'decision_path_version')) {
                $table->string('decision_path_version')->nullable()->after('decision_path_key');
            }
            if (!Schema::hasColumn('evaluation_sessions', 'catalog_version')) {
                $table->unsignedInteger('catalog_version')->nullable()->after('decision_path_version');
            }
            if (!Schema::hasColumn('evaluation_sessions', 'risk_model_version')) {
                $table->unsignedInteger('risk_model_version')->nullable()->after('catalog_version');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_sessions', 'risk_model_version')) {
                $table->dropColumn('risk_model_version');
            }
            if (Schema::hasColumn('evaluation_sessions', 'catalog_version')) {
                $table->dropColumn('catalog_version');
            }
            if (Schema::hasColumn('evaluation_sessions', 'decision_path_version')) {
                $table->dropColumn('decision_path_version');
            }
            if (Schema::hasColumn('evaluation_sessions', 'decision_path_key')) {
                $table->dropColumn('decision_path_key');
            }
            if (Schema::hasColumn('evaluation_sessions', 'current_node_key')) {
                $table->dropColumn('current_node_key');
            }
        });
    }
};
