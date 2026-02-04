<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dynamic_question_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('dynamic_question_templates', 'routing_rules')) {
                $table->json('routing_rules')->nullable()->after('visible_if');
            }
        });
    }

    public function down(): void
    {
        Schema::table('dynamic_question_templates', function (Blueprint $table) {
            if (Schema::hasColumn('dynamic_question_templates', 'routing_rules')) {
                $table->dropColumn('routing_rules');
            }
        });
    }
};
