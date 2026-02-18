<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluation_sessions', 'path_trace')) {
                $table->json('path_trace')->nullable()->after('visited_blocs');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_sessions', 'path_trace')) {
                $table->dropColumn('path_trace');
            }
        });
    }
};