<?php

namespace Database\Seeders\Evaluation;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bloc `patient_context` — collecte minimale pour le filtrage précoce.
 *
 * Contient uniquement l'âge (date de naissance) et la commune afin de :
 *  - Détecter les mineurs (< 18 ans) et les rediriger immédiatement.
 *  - Rendre disponible l'âge pour les conditions visible_if des blocs
 *    symptomatiques (symptom_cervix, symptom_cervical_light).
 *
 * Placé en position 2 (juste après le greeting) pour que ces données
 * soient connues avant l'écran d'urgence et le dispatch symptomatique.
 */
class PatientContextSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();

        $group = QuestionGroup::where('key', 'profil_identite')->firstOrFail();

        $startYear = date('Y') - 90;
        $endYear   = date('Y') - 18;

        $communeOptions = DB::table('communes')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => ['value' => $row->name, 'label' => $row->name])
            ->toArray();

        $questions = [
            [
                'key'            => 'birth_date',
                'text'           => 'Choisir votre année de naissance',
                'type'           => 'select_one',
                'bloc_key'       => 'patient_context',
                'answer_target'  => 'personal_profile',
                'order'          => 1,
                'is_entry_point' => true,
                'options'        => array_map(
                    fn ($y) => ['value' => sprintf('%04d-01-01', $y), 'label' => (string) $y],
                    range($endYear, $startYear)
                ),
                'routing_rules' => [
                    // Mineur (< 18 ans) → sortie immédiate vers bloc consent_declined
                    ['if' => ['source' => 'answer', 'operator' => 'age_lt', 'value' => 18], 'next' => 'question:consent_declined_thank_you'],
                ],
            ],
            [
                'key'           => 'commune',
                'text'          => 'Dans quelle commune habitez-vous ?',
                'type'          => 'select_one',
                'bloc_key'      => 'patient_context',
                'answer_target' => 'personal_profile',
                'order'         => 2,
                'options'       => $communeOptions,
            ],
        ];

        $validator->assertUniqueOrderPerBloc($questions, static::class);

        foreach ($questions as $q) {
            $q = $validator->normalize($q);
            $validator->assertValid($q);

            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                array_merge($q, ['question_group_id' => $group->id])
            );
        }
    }
}
