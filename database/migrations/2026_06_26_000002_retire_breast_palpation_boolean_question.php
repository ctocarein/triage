<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retire la question booleenne 'breast_palpation' du parcours.
 *
 * Elle faisait double emploi avec 'breast_palpation_experience', qui pose la
 * meme chose en trois niveaux, et la condition de visibilite qui les reliait
 * rendait toujours une option incoherente avec le public qui la recevait.
 * L'explicatif est desormais montre a toutes.
 *
 * La question est desactivee et non supprimee : les reponses deja enregistrees
 * la referencent par dynamic_question_template_id, et les sessions passees
 * doivent rester relisibles telles qu'elles ont ete conduites.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('dynamic_question_templates')
            ->where('key', 'breast_palpation')
            ->update(['is_active' => false]);

        // 'breast_palpation_experience' devient l'entree du bloc et n'est plus
        // conditionnee. Les bases fraiches recoivent cet etat par le seeder.
        DB::table('dynamic_question_templates')
            ->where('key', 'breast_palpation_experience')
            ->update([
                'visible_if'     => null,
                'is_entry_point' => true,
                'order'          => 1,
            ]);
    }

    public function down(): void
    {
        DB::table('dynamic_question_templates')
            ->where('key', 'breast_palpation')
            ->update(['is_active' => true]);

        DB::table('dynamic_question_templates')
            ->where('key', 'breast_palpation_experience')
            ->update([
                'visible_if'     => json_encode([
                    ['source' => 'question', 'key' => 'breast_palpation', 'value' => true, 'operator' => '='],
                ]),
                'is_entry_point' => false,
                'order'          => 2,
            ]);
    }
};
