<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('decision_path_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('decision_path_id')->nullable(false)->change();
        });
    }
};
