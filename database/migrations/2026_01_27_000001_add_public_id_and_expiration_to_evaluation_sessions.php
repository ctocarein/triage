<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluation_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluation_sessions', 'public_id')) {
                $table->uuid('public_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('evaluation_sessions', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('completed_at');
            }
        });

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE evaluation_sessions MODIFY COLUMN status ENUM('in_progress','completed','cancelled','expired') NOT NULL DEFAULT 'in_progress'");
            DB::statement("UPDATE evaluation_sessions SET public_id = UUID() WHERE public_id IS NULL");
        } elseif ($driver === 'pgsql') {
            DB::statement("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'evaluation_sessions_status_enum') THEN CREATE TYPE evaluation_sessions_status_enum AS ENUM ('in_progress','completed','cancelled','expired'); END IF; END $$;");
            DB::statement("ALTER TABLE evaluation_sessions ALTER COLUMN status TYPE evaluation_sessions_status_enum USING status::text::evaluation_sessions_status_enum");
        }

        if ($driver !== 'mysql') {
            DB::table('evaluation_sessions')
                ->whereNull('public_id')
                ->orderBy('id')
                ->chunkById(200, function ($sessions): void {
                    foreach ($sessions as $session) {
                        DB::table('evaluation_sessions')
                            ->where('id', $session->id)
                            ->update(['public_id' => (string) Str::uuid()]);
                    }
                });
        }

        Schema::table('evaluation_sessions', function (Blueprint $table) {
            $table->unique('public_id');
        });
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE evaluation_sessions MODIFY COLUMN status ENUM('in_progress','completed','cancelled') NOT NULL DEFAULT 'in_progress'");
        }

        Schema::table('evaluation_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('evaluation_sessions', 'public_id')) {
                $table->dropUnique(['public_id']);
                $table->dropColumn('public_id');
            }
            if (Schema::hasColumn('evaluation_sessions', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });
    }
};
