<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\QuestionTemplateContractValidator;

class PersonalProfileQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();

        // Recupere tous les groupes necessaires en une seule fois
        $groups = QuestionGroup::whereIn('key', [
            'profile_personal', 'profil_identite', 'profil_socio', 'profil_sante',
        ])->get()->keyBy('key');

        $communeOptions = DB::table('communes')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => ['value' => $row->name, 'label' => $row->name])
            ->toArray();

        $startYear = date('Y') - 90;
        $endYear = date('Y') - 18;

        $questions = [
            [
                'key' => 'first_name',
                'text' => 'Quel est votre prénom ?',
                'type' => 'text',
                'bloc_key' => 'profil_identite',
                'answer_target' => 'personal_profile',
                'order' => 1,
                'is_entry_point' => true,
            ],
            [
                'key' => 'birth_date',
                'text' => 'Puis-je avoir votre année de naissance ?',
                'type' => 'date',
                'bloc_key' => 'profil_identite',
                'answer_target' => 'personal_profile',
                'options' => array_map(fn ($y) => ['value' => (string) $y, 'label' => (string) $y], range($endYear, $startYear)),
                'order' => 2,
                'is_entry_point' => true,
            ],
            // [
            //     'key' => 'language',
            //     'text' => 'La langue que vous parlez le mieux ?',
            //     'type' => 'text',
            //     'bloc_key' => 'profil_socio',
            //     'answer_target' => 'personal_profile',
            //     'order' => 3,
            // ],
            [
                'key' => 'commune',
                'text' => 'Dans quelle commune habitez-vous ?',
                'type' => 'select_one',
                'bloc_key' => 'profil_socio',
                'options' => $communeOptions,
                'answer_target' => 'personal_profile',
                'order' => 4,
            ],
            [
                'key' => 'cmu',
                'text' => 'Bénéficiez-vous de la Couverture Maladie Universelle (CMU) ?',
                'type' => 'boolean',
                'bloc_key' => 'profil_socio',
                'answer_target' => 'personal_profile',
                'order' => 5,
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => true], 'next' => 'question:cmu_number'],
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => false], 'next' => 'question:marital_status'],
                ],
            ],
            [
                'key' => 'marital_status',
                'text' => 'Quel est votre statut matrimonial ?',
                'type' => 'select_one',
                'bloc_key' => 'profil_socio',
                'options' => ['Célibataire', 'Marié(e)', 'Divorcé(e)', 'Veuf(ve)'],
                'answer_target' => 'personal_profile',
                'order' => 6,
            ],
            [
                'key' => 'education_level',
                'text' => 'Quel est votre niveau d\'éducation ?',
                'type' => 'select_one',
                'bloc_key' => 'profil_socio',
                'options' => ['Aucun', 'Primaire', 'Secondaire', 'Supérieur', 'Autodidacte'],
                'answer_target' => 'personal_profile',
                'order' => 7,
            ],
            [
                'key' => 'occupation',
                'text' => 'Quelle est votre profession ou activité principale ?',
                'type' => 'text',
                'bloc_key' => 'profil_socio',
                'answer_target' => 'personal_profile',
                'order' => 8,
            ],
        ];

        $validator->assertUniqueOrderPerBloc($questions, static::class);

        foreach ($questions as $q) {
            // Trouve le sous-groupe, sinon fallback sur le groupe principal
            $questionGroup = $groups[$q['bloc_key']] ?? $groups['profile_personal'];
            $q = $validator->normalize($q);
            $validator->assertValid($q);

            DynamicQuestionTemplate::updateOrCreate(
                [
                    'key' => $q['key'],
                ],
                [
                    'text' => $q['text'],
                    'type' => $q['type'],
                    'bloc_key' => $q['bloc_key'] ?? null,
                    'question_group_id' => $questionGroup->id,
                    'answer_target' => $q['answer_target'],
                    'options' => isset($q['options']) ? json_encode($q['options']) : null,
                    'visible_if' => $q['visible_if'] ?? null,
                    'risk_weight' => $q['risk_weight'] ?? 0,
                    'order' => $q['order'] ?? 0,
                    'is_entry_point' => $q['is_entry_point'] ?? false,
                    'is_required' => true,
                    // 'image_url' => $q['image_url'] ?? null, // si tu veux ajouter une image plus tard
                    // Les timestamps sont automatiques si Eloquent gere $timestamps = true
                ]
            );
        }
    }
}
