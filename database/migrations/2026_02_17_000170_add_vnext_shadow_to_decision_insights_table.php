<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('decision_insights', function (Blueprint $table) {
            if (! Schema::hasColumn('decision_insights', 'vnext_shadow')) {
                $table->json('vnext_shadow')->nullable()->after('final_decision');
            }
        });
    }

    public function down(): void
    {
        Schema::table('decision_insights', function (Blueprint $table) {
            if (Schema::hasColumn('decision_insights', 'vnext_shadow')) {
                $table->dropColumn('vnext_shadow');
            }
        });
    }
};