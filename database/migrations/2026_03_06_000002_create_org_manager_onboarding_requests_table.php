<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_manager_onboarding_requests', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->uuid('identity_id');
            $table->uuid('organization_id');

            // Suivi de la vérification email
            $table->string('email_verification_token', 64)->unique()->nullable();
            $table->timestamp('email_verification_sent_at')->nullable();

            // Numéro de tentative (max 3)
            $table->tinyInteger('attempt_number')->default(1);

            // Statut de la demande
            $table->enum('status', [
                'email_unverified',    // email non encore confirmé
                'pending_validation',  // email confirmé, attente admin
                'approved',            // validé par admin
                'rejected',            // rejeté par admin
            ])->default('email_unverified');

            // Motif de rejet (choix admin + texte libre)
            $table->string('rejected_reason_code')->nullable(); // incomplete_info | unknown_org | duplicate | other
            $table->text('rejected_reason_detail')->nullable();

            // Suivi des rappels admin
            $table->timestamp('admin_notified_at')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
            $table->tinyInteger('reminder_count')->default(0);

            $table->timestamps();

            $table->index(['identity_id', 'status']);
            $table->index(['status', 'admin_notified_at']); // pour le job de rappels
            $table->foreign('identity_id')->references('id')->on('identities')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_manager_onboarding_requests');
    }
};
