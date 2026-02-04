<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluation_sessions', 'referral_id')) {
                $table->uuid('referral_id')->nullable()->after('expires_at');
                $table->index('referral_id');
                $table->foreign('referral_id')->references('id')->on('ambassador_referrals')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('evaluation_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_sessions', 'referral_id')) {
                $table->dropForeign(['referral_id']);
                $table->dropIndex(['referral_id']);
                $table->dropColumn('referral_id');
            }
        });
    }
};
