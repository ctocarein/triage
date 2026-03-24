<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thematic_blocs', function (Blueprint $table) {
            $table->string('intention')->default('assessment')
                ->comment('Intention clinique du thème : assessment, screening, monitoring, preparation, follow_up, education, triage, shared_decision, crisis.')
                ->after('description');

            $table->unsignedSmallInteger('priority')->default(0)
                ->comment('Priorité d\'affichage quand plusieurs thèmes sont disponibles simultanément. crisis prime toujours indépendamment.')
                ->after('intention');

            $table->json('trigger_conditions')->nullable()
                ->comment('Conditions JSON de déclenchement automatique (score, profil, réponse). null = toujours disponible au patient.')
                ->after('priority');

            $table->string('recurrence_policy')->default('once')
                ->comment('Politique de répétition : once | periodic | conditional.')
                ->after('trigger_conditions');
        });
    }

    public function down(): void
    {
        Schema::table('thematic_blocs', function (Blueprint $table) {
            $table->dropColumn(['intention', 'priority', 'trigger_conditions', 'recurrence_policy']);
        });
    }
};
