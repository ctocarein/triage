<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;

class PersonalProfileQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();

        $group = QuestionGroup::where('key', 'profil_identite')->first()
            ?? QuestionGroup::where('key', 'profile_personal')->firstOrFail();

        $communeOptions = DB::table('communes')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => ['value' => $row->name, 'label' => $row->name])
            ->toArray();

        $startYear = date('Y') - 90;
        $endYear   = date('Y') - 18;

        // Toutes les questions dans un seul bloc `profil_identite`.
        // Application 100% féminine : genre = F par défaut, non demandé.
        $questions = [
            [
                'key'            => 'first_name',
                'text'           => 'Quel est votre prénom ?',
                'type'           => 'text',
                'bloc_key'       => 'profil_identite',
                'answer_target'  => 'personal_profile',
                'order'          => 1,
                'is_entry_point' => true,
            ],
            [
                'key'            => 'birth_date',
                'text'           => 'Puis-je avoir votre année de naissance ?',
                'type'           => 'date',
                'bloc_key'       => 'profil_identite',
                'answer_target'  => 'personal_profile',
                'options'        => array_map(
                    fn ($y) => ['value' => (string) $y, 'label' => (string) $y],
                    range($endYear, $startYear)
                ),
                'order'          => 2,
                'is_entry_point' => true,
            ],
            [
                'key'           => 'commune',
                'text'          => 'Dans quelle commune habitez-vous ?',
                'type'          => 'select_one',
                'bloc_key'      => 'profil_identite',
                'answer_target' => 'personal_profile',
                'options'       => $communeOptions,
                'order'         => 3,
            ],
            [
                'key'           => 'cmu',
                'text'          => 'Bénéficiez-vous de la Couverture Maladie Universelle (CMU) ?',
                'type'          => 'boolean',
                'bloc_key'      => 'profil_identite',
                'answer_target' => 'personal_profile',
                'order'         => 4,
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => true],  'next' => 'question:cmu_number'],
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => false], 'next' => 'question:marital_status'],
                ],
            ],
            [
                'key'           => 'marital_status',
                'text'          => 'Quel est votre statut matrimonial ?',
                'type'          => 'select_one',
                'bloc_key'      => 'profil_identite',
                'answer_target' => 'personal_profile',
                'options'       => ['Célibataire', 'Marié(e)', 'Divorcé(e)', 'Veuf(ve)'],
                'order'         => 5,
            ],
            [
                'key'           => 'education_level',
                'text'          => 'Quel est votre niveau d\'éducation ?',
                'type'          => 'select_one',
                'bloc_key'      => 'profil_identite',
                'answer_target' => 'personal_profile',
                'options'       => ['Aucun', 'Primaire', 'Secondaire', 'Supérieur', 'Autodidacte'],
                'order'         => 6,
            ],
            [
                'key'           => 'occupation',
                'text'          => 'Quelle est votre profession ou activité principale ?',
                'type'          => 'text',
                'bloc_key'      => 'profil_identite',
                'answer_target' => 'personal_profile',
                'order'         => 7,
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
