<?php

namespace Database\Seeders\Evaluation;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\QuestionGroup;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PersonalProfileQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator;

        $group = QuestionGroup::where('key', 'profil_identite')->first()
            ?? QuestionGroup::where('key', 'profile_personal')->firstOrFail();

        $communeOptions = DB::table('communes')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => ['value' => $row->name, 'label' => $row->name])
            ->toArray();

        $startYear = date('Y') - 90;
        $endYear = date('Y') - 18;

        $profileIdentityQuestions = [
            [
                'key' => 'first_name',
                'text' => 'Puis-je avoir votre prénom ?',
                'type' => 'text',
                'bloc_key' => 'profile_identity',
                'answer_target' => 'personal_profile',
                'order' => 1,
                'is_entry_point' => true,
            ],
            [
                'key' => 'birth_date',
                'text' => 'Choisir votre année de naissance',
                'type' => 'select_one',
                'bloc_key' => 'profile_identity',
                'answer_target' => 'personal_profile',
                'options' => array_map(
                    fn ($y) => ['value' => sprintf('%04d-01-01', $y), 'label' => (string) $y],
                    range($endYear, $startYear)
                ),
                'order' => 2,
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => 'age_lt', 'value' => 18], 'next' => 'bloc:consent_declined'],
                    ['if' => ['source' => 'answer', 'operator' => 'age_gte', 'value' => 18], 'next' => 'question:commune'],
                ],
            ],
            [
                'key' => 'commune',
                'text' => 'Dans quelle commune habitez-vous ?',
                'type' => 'select_one',
                'bloc_key' => 'profile_identity',
                'answer_target' => 'personal_profile',
                'options' => $communeOptions,
                'order' => 3,
       ],
            [
                'key' => 'cmu',
                'text' => 'Bénéficiez-vous de la Couverture Maladie Universelle (CMU) ?',
                'type' => 'boolean',
                'bloc_key' => 'profile_identity',
                'answer_target' => 'personal_profile',
                'order' => 4,
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => true], 'next' => 'question:cmu_number'],
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => false], 'next' => 'bloc:lifestyle'],
                ],
       ],
            [
                'key' => 'cmu_number',
                'text' => 'Quel est votre numéro de CMU ?',
                'type' => 'text',
                'bloc_key' => 'profile_identity',
                'answer_target' => 'personal_profile',
                'order' => 5,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'cmu', 'operator' => '=', 'value' => true],
                ]),
                'routing_rules' => [
                    ['if' => true, 'next' => 'bloc:lifestyle'],
                ],
       ],
        ];
        $validator->assertUniqueOrderPerBloc($profileIdentityQuestions, static::class);

        foreach ($profileIdentityQuestions as $q) {
            $q = $validator->normalize($q);
            $validator->assertValid($q);

            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                array_merge($q, ['question_group_id' => $group->id])
            );
        }
    }
}
