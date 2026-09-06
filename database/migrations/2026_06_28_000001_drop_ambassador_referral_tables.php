<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supprime les tables du parrainage, devenu sans objet.
 *
 * ATTENTION -- migration destructive et irreversible.
 *
 * Sept tables contenant des donnees reelles disparaissent, ainsi que la colonne
 * referral_id de evaluation_sessions. Le down() recree des structures vides :
 * il retablit le schema, jamais les donnees.
 *
 * Elle refuse donc de s'executer tant que EVALUATION_ALLOW_AMBASSADOR_DROP
 * n'est pas explicitement positionne. Un `php artisan migrate` lance pour une
 * autre raison echoue bruyamment plutot que d'effacer sans le dire -- ce qui
 * est le comportement recherche : le rattrapage n'existe pas, seule une
 * sauvegarde prealable protege.
 *
 * Avant de l'activer : faire un dump de la base.
 */
return new class extends Migration
{
    /**
     * Ordre inverse des dependances : les tables referencantes d'abord.
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'pending_referral_clicks',
        'ambassador_audit_logs',
        'ambassador_badges',
        'ambassador_levels',
        'ambassador_referral_usages',
        'ambassador_referrals',
        'ambassadors',
    ];

    public function up(): void
    {
        // Reservee a MySQL/MariaDB, ou vivent les donnees a nettoyer.
        //
        // SQLite ne sait pas faire cette suppression : Laravel declare la cle
        // etrangere au niveau de la table, et SQLite refuse de supprimer une
        // colonne encore citee dans une telle clause. Il faudrait reconstruire
        // entierement evaluation_sessions -- une vingtaine de colonnes a
        // reenumerer, qu'il faudrait maintenir a chaque evolution du schema.
        // Pour un nettoyage ponctuel, c'est plus de risque que ce qu'on retire.
        //
        // Consequence assumee : la base de test conserve ces tables vides. Elles
        // ne genent rien, plus aucun code ne les atteint.
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $this->assertExplicitlyAllowed();

        // La contrainte d'abord : MySQL refuse de supprimer une table encore
        // referencee.
        $this->dropReferralColumn();

        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        // Le schema seul est retabli. Les donnees supprimees ne reviennent pas :
        // ce down() sert a ne pas bloquer un rollback, pas a reparer.
        Schema::table('evaluation_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('evaluation_sessions', 'referral_id')) {
                $table->uuid('referral_id')->nullable();
                $table->index('referral_id');
            }
        });
    }

    private function assertExplicitlyAllowed(): void
    {
        if (filter_var(env('EVALUATION_ALLOW_AMBASSADOR_DROP', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        throw new RuntimeException(
            'Migration destructive non autorisee. Sept tables de parrainage et la colonne '
            .'evaluation_sessions.referral_id seraient supprimees sans retour possible. '
            .'Faire un dump de la base, puis relancer avec EVALUATION_ALLOW_AMBASSADOR_DROP=true.'
        );
    }

    private function dropReferralColumn(): void
    {
        if (! Schema::hasColumn('evaluation_sessions', 'referral_id')) {
            return;
        }

        Schema::table('evaluation_sessions', function (Blueprint $table): void {
            $table->dropForeign(['referral_id']);
            $table->dropIndex(['referral_id']);
            $table->dropColumn('referral_id');
        });
    }
};
