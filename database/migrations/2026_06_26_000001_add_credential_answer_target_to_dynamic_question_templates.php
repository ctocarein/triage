<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute la cible 'credential' a answer_target : le numero de telephone collecte
 * pendant l'evaluation alimente les identifiants de connexion, qui ne vivent pas
 * dans personal_profiles.
 *
 * Reservee a MySQL/MariaDB, ou l'enum doit etre modifie en place. Les bases
 * creees de zero (SQLite en test) recoivent la valeur par la migration de
 * creation, qui a ete mise a jour en consequence.
 */
return new class extends Migration
{
    private const TARGETS = "'personal_profile','medical_profile','evaluation','followup','tracking','survey','greeting','custom','consent'";

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement(
            'ALTER TABLE dynamic_question_templates MODIFY answer_target ENUM('.self::TARGETS.",'credential') NULL"
        );
    }

    public function down(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::table('dynamic_question_templates')
            ->where('answer_target', 'credential')
            ->update(['answer_target' => null]);

        DB::statement(
            'ALTER TABLE dynamic_question_templates MODIFY answer_target ENUM('.self::TARGETS.') NULL'
        );
    }
};
