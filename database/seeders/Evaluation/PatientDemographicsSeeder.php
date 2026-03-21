<?php

namespace Database\Seeders\Evaluation;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;
use Illuminate\Database\Seeder;

/**
 * Bloc `patient_demographics` — données démographiques complètes.
 *
 * Collecte le prénom et la couverture maladie (CMU).
 * Placé en position 14 (dernier bloc avant la fin du parcours) car ces données
 * sont moins critiques pour le scoring et servent principalement à personnaliser
 * le message de restitution.
 */
class PatientDemographicsSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();
        $group     = QuestionGroup::where('key', 'profil_socio')->firstOrFail();

        $questions = [
            [
                'key'            => 'first_name',
                'text'           => 'Puis-je avoir votre prénom ?',
                'type'           => 'text',
                'bloc_key'       => 'patient_demographics',
                'answer_target'  => 'personal_profile',
                'order'          => 1,
                'is_entry_point' => true,
            ],
            [
                'key'           => 'cmu',
                'text'          => 'Bénéficiez-vous de la Couverture Maladie Universelle (CMU) ?',
                'type'          => 'boolean',
                'bloc_key'      => 'patient_demographics',
                'answer_target' => 'personal_profile',
                'order'         => 2,
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => true], 'next' => 'question:cmu_number'],
                ],
            ],
            [
                'key'           => 'cmu_number',
                'text'          => 'Quel est votre numéro de CMU ?',
                'type'          => 'text',
                'bloc_key'      => 'patient_demographics',
                'answer_target' => 'personal_profile',
                'order'         => 3,
                'visible_if'    => json_encode([
                    ['source' => 'question', 'key' => 'cmu', 'operator' => '=', 'value' => true],
                ]),
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
