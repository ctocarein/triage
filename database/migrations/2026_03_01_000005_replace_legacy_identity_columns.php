<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        $dropForeignIfExists = function (string $table, string $column): void {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                return;
            }

            try {
                Schema::table($table, function (Blueprint $table) use ($column) {
                    $table->dropForeign([$column]);
                });
            } catch (\Throwable $e) {
                // Foreign key missing or already dropped.
            }
        };

        $dropColumnIfExists = function (string $table, string $column): void {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        };

        $ensureForeignKey = function (
            string $table,
            string $column,
            string $referencedTable,
            string $onDelete = 'cascade'
        ): void {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                return;
            }

            try {
                Schema::table($table, function (Blueprint $table) use ($column, $referencedTable, $onDelete) {
                    $foreign = $table->foreign($column)->references('id')->on($referencedTable);

                    if ($onDelete === 'set_null') {
                        $foreign->nullOnDelete();
                    } elseif ($onDelete === 'cascade') {
                        $foreign->cascadeOnDelete();
                    }
                });
            } catch (\Throwable $e) {
                // Foreign key already exists or cannot be added.
            }
        };

        $dropForeignIfExists('personal_profiles', 'account_id');
        $dropColumnIfExists('personal_profiles', 'account_id');
        $dropForeignIfExists('medical_profiles', 'account_id');
        $dropColumnIfExists('medical_profiles', 'account_id');
        $dropForeignIfExists('evaluation_sessions', 'account_id');
        $dropColumnIfExists('evaluation_sessions', 'account_id');
        $dropForeignIfExists('reports', 'account_id');
        $dropColumnIfExists('reports', 'account_id');
        $dropForeignIfExists('scan_events', 'account_id');
        $dropColumnIfExists('scan_events', 'account_id');
        $dropForeignIfExists('scan_events', 'user_id');
        $dropColumnIfExists('scan_events', 'user_id');
        $dropForeignIfExists('decision_insights', 'account_id');
        $dropColumnIfExists('decision_insights', 'account_id');
        $dropForeignIfExists('uploads', 'account_id');
        $dropColumnIfExists('uploads', 'account_id');
        $dropForeignIfExists('user_reminders', 'user_id');
        $dropColumnIfExists('user_reminders', 'user_id');
        $dropForeignIfExists('campaigns', 'tenant_id');
        $dropColumnIfExists('campaigns', 'tenant_id');
        $dropForeignIfExists('qr_codes', 'tenant_id');
        $dropColumnIfExists('qr_codes', 'tenant_id');
        $dropForeignIfExists('campaigns', 'created_by');
        $dropColumnIfExists('campaigns', 'created_by');
        $dropForeignIfExists('campaigns', 'validated_by');
        $dropColumnIfExists('campaigns', 'validated_by');
        $dropForeignIfExists('qr_codes', 'created_by');
        $dropColumnIfExists('qr_codes', 'created_by');

        Schema::enableForeignKeyConstraints();

        if (Schema::hasTable('personal_profiles') && ! Schema::hasColumn('personal_profiles', 'identity_id')) {
            Schema::table('personal_profiles', function (Blueprint $table) {
                $table->uuid('identity_id');
                $table->index('identity_id');
                $table->foreign('identity_id')->references('id')->on('identities')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('medical_profiles') && ! Schema::hasColumn('medical_profiles', 'identity_id')) {
            Schema::table('medical_profiles', function (Blueprint $table) {
                $table->uuid('identity_id');
                $table->index('identity_id');
                $table->foreign('identity_id')->references('id')->on('identities')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('evaluation_sessions') && ! Schema::hasColumn('evaluation_sessions', 'identity_id')) {
            Schema::table('evaluation_sessions', function (Blueprint $table) {
                $table->uuid('identity_id');
                $table->index('identity_id');
                $table->foreign('identity_id')->references('id')->on('identities')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('reports') && ! Schema::hasColumn('reports', 'identity_id')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->uuid('identity_id')->nullable();
                $table->index('identity_id');
                $table->foreign('identity_id')->references('id')->on('identities')->nullOnDelete();
            });
        }

        if (Schema::hasTable('scan_events') && ! Schema::hasColumn('scan_events', 'identity_id')) {
            Schema::table('scan_events', function (Blueprint $table) {
                $table->uuid('identity_id')->nullable();
                $table->index(['identity_id', 'scanned_at']);
                $table->foreign('identity_id')->references('id')->on('identities')->nullOnDelete();
            });
        }

        if (Schema::hasTable('decision_insights') && ! Schema::hasColumn('decision_insights', 'identity_id')) {
            Schema::table('decision_insights', function (Blueprint $table) {
                $table->uuid('identity_id')->nullable();
                $table->index('identity_id');
                $table->foreign('identity_id')->references('id')->on('identities')->nullOnDelete();
            });
        }

        if (Schema::hasTable('uploads') && ! Schema::hasColumn('uploads', 'identity_id')) {
            Schema::table('uploads', function (Blueprint $table) {
                $table->uuid('identity_id')->nullable();
                $table->index('identity_id');
                $table->foreign('identity_id')->references('id')->on('identities')->nullOnDelete();
            });
        }

        if (Schema::hasTable('user_reminders') && ! Schema::hasColumn('user_reminders', 'identity_id')) {
            Schema::table('user_reminders', function (Blueprint $table) {
                $table->uuid('identity_id');
                $table->index('identity_id');
                $table->foreign('identity_id')->references('id')->on('identities')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('campaigns') && ! Schema::hasColumn('campaigns', 'organization_id')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->uuid('organization_id');
                $table->index(['organization_id', 'status']);
                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('qr_codes') && ! Schema::hasColumn('qr_codes', 'organization_id')) {
            Schema::table('qr_codes', function (Blueprint $table) {
                $table->uuid('organization_id');
                $table->index(['organization_id', 'status']);
                $table->index(['organization_id', 'type']);
                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('campaigns') && ! Schema::hasColumn('campaigns', 'created_by')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->uuid('created_by');
                $table->index('created_by');
                $table->foreign('created_by')->references('id')->on('identities')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('campaigns') && ! Schema::hasColumn('campaigns', 'validated_by')) {
            Schema::table('campaigns', function (Blueprint $table) {
                $table->uuid('validated_by')->nullable();
                $table->index('validated_by');
                $table->foreign('validated_by')->references('id')->on('identities')->nullOnDelete();
            });
        }

        if (Schema::hasTable('qr_codes') && ! Schema::hasColumn('qr_codes', 'created_by')) {
            Schema::table('qr_codes', function (Blueprint $table) {
                $table->uuid('created_by')->nullable();
                $table->index('created_by');
                $table->foreign('created_by')->references('id')->on('identities')->nullOnDelete();
            });
        }

        $ensureForeignKey('personal_profiles', 'identity_id', 'identities');
        $ensureForeignKey('medical_profiles', 'identity_id', 'identities');
        $ensureForeignKey('evaluation_sessions', 'identity_id', 'identities');
        $ensureForeignKey('reports', 'identity_id', 'identities', 'set_null');
        $ensureForeignKey('scan_events', 'identity_id', 'identities', 'set_null');
        $ensureForeignKey('decision_insights', 'identity_id', 'identities', 'set_null');
        $ensureForeignKey('uploads', 'identity_id', 'identities', 'set_null');
        $ensureForeignKey('user_reminders', 'identity_id', 'identities');
        $ensureForeignKey('campaigns', 'organization_id', 'organizations');
        $ensureForeignKey('qr_codes', 'organization_id', 'organizations');
        $ensureForeignKey('campaigns', 'created_by', 'identities');
        $ensureForeignKey('campaigns', 'validated_by', 'identities', 'set_null');
        $ensureForeignKey('qr_codes', 'created_by', 'identities', 'set_null');
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        $dropForeignIfExists = function (string $table, string $column): void {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                return;
            }

            try {
                Schema::table($table, function (Blueprint $table) use ($column) {
                    $table->dropForeign([$column]);
                });
            } catch (\Throwable $e) {
                // Foreign key missing or already dropped.
            }
        };

        $dropColumnIfExists = function (string $table, string $column): void {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                Schema::table($table, function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        };

        $dropForeignIfExists('personal_profiles', 'identity_id');
        $dropColumnIfExists('personal_profiles', 'identity_id');
        $dropForeignIfExists('medical_profiles', 'identity_id');
        $dropColumnIfExists('medical_profiles', 'identity_id');
        $dropForeignIfExists('evaluation_sessions', 'identity_id');
        $dropColumnIfExists('evaluation_sessions', 'identity_id');
        $dropForeignIfExists('reports', 'identity_id');
        $dropColumnIfExists('reports', 'identity_id');
        $dropForeignIfExists('scan_events', 'identity_id');
        $dropColumnIfExists('scan_events', 'identity_id');
        $dropForeignIfExists('decision_insights', 'identity_id');
        $dropColumnIfExists('decision_insights', 'identity_id');
        $dropForeignIfExists('uploads', 'identity_id');
        $dropColumnIfExists('uploads', 'identity_id');
        $dropForeignIfExists('user_reminders', 'identity_id');
        $dropColumnIfExists('user_reminders', 'identity_id');
        $dropForeignIfExists('campaigns', 'organization_id');
        $dropColumnIfExists('campaigns', 'organization_id');
        $dropForeignIfExists('qr_codes', 'organization_id');
        $dropColumnIfExists('qr_codes', 'organization_id');
        $dropForeignIfExists('campaigns', 'created_by');
        $dropColumnIfExists('campaigns', 'created_by');
        $dropForeignIfExists('campaigns', 'validated_by');
        $dropColumnIfExists('campaigns', 'validated_by');
        $dropForeignIfExists('qr_codes', 'created_by');
        $dropColumnIfExists('qr_codes', 'created_by');

        Schema::enableForeignKeyConstraints();
    }
};
