<?php

namespace Database\Seeders\Evaluation;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;
use Illuminate\Database\Seeder;

/**
 * Bloc `screening_history` — historique de dépistage.
 *
 * Date, type et résultat du dernier dépistage (frottis, mammographie…).
 * Placé en position 13, après `lifestyle` (12) et avant `patient_demographics` (14).
 */
class ScreeningHistorySeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();
        $group     = QuestionGroup::where('key', 'profil_sante')->firstOrFail();

        $questions = [
            [
                'key'            => 'last_screening_date',
                'text'           => 'Quelle est la date de votre dernier dépistage (frottis, mammographie…) ?',
                'type'           => 'select_one',
                'bloc_key'       => 'screening_history',
                'answer_target'  => 'medical_profile',
                'order'          => 1,
                'is_entry_point' => true,
                'options'        => json_encode([
                    ['value' => 'never',   'label' => 'Je n\'ai jamais fait de dépistage'],
                    ['value' => 'lt_1y',   'label' => 'Il y a moins d\'un an'],
                    ['value' => 'gt_3y',   'label' => 'Il y a plus de 3 ans'],
                    ['value' => 'unknown', 'label' => 'Je ne me souviens plus de la date exacte'],
                ]),
            ],
            [
                'key'           => 'screening_type',
                'text'          => 'Quel type de dépistage avez-vous effectué en dernier ?',
                'type'          => 'select_one',
                'bloc_key'      => 'screening_history',
                'answer_target' => 'medical_profile',
                'order'         => 2,
                'visible_if'    => json_encode([
                    ['source' => 'question', 'key' => 'last_screening_date', 'operator' => '!=', 'value' => 'never'],
                ]),
                'options'       => json_encode([
                    ['value' => 'frottis',      'label' => 'Frottis (col de l\'utérus)'],
                    ['value' => 'mammographie', 'label' => 'Mammographie'],
                    ['value' => 'autre',        'label' => 'Autre'],
                ]),
            ],
            [
                'key'           => 'last_screening_result',
                'text'          => 'Quel était le résultat de votre dernier dépistage ?',
                'type'          => 'select_one',
                'bloc_key'      => 'screening_history',
                'answer_target' => 'medical_profile',
                'order'         => 3,
                'visible_if'    => json_encode([
                    ['source' => 'question', 'key' => 'last_screening_date', 'operator' => '!=', 'value' => 'never'],
                ]),
                'options'       => json_encode([
                    ['value' => 'normal',               'label' => 'Normal'],
                    ['value' => 'abnormal',             'label' => 'Anormal, avec suivi médical'],
                    ['value' => 'abnormal_no_followup', 'label' => 'Anormal, sans suivi médical'],
                    ['value' => 'dont_know',            'label' => 'Je ne sais pas'],
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
