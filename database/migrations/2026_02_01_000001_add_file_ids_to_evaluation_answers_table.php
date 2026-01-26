<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_answers', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluation_answers', 'file_ids')) {
                $table->json('file_ids')->nullable()->after('value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_answers', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_answers', 'file_ids')) {
                $table->dropColumn('file_ids');
            }
        });
    }
};
