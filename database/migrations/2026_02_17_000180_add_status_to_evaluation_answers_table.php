<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluation_answers', 'status')) {
                $table->string('status', 30)->default('answered')->after('is_skipped');
                $table->index(['evaluation_session_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_answers', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_answers', 'status')) {
                $table->dropIndex(['evaluation_session_id', 'status']);
                $table->dropColumn('status');
            }
        });
    }
};