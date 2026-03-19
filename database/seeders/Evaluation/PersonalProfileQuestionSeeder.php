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
        $validator = new QuestionTemplateContractValidator();

        $group = QuestionGroup::where('key', 'profil_identite')->first()
            ?? QuestionGroup::where('key', 'profile_personal')->firstOrFail();

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
                'text' => 'Puis-je avoir votre prénom ?',
                'type' => 'text',
                'bloc_key' => 'profil_identite',
                'answer_target' => 'personal_profile',
                'order' => 1,
                'is_entry_point' => true,
            ],
            [
                'key' => 'birth_date',
                'text' => 'Choisir votre année de naissance',
                'type' => 'select_one',
                'bloc_key' => 'profil_identite',
                'answer_target' => 'personal_profile',
                'options' => array_map(
                    fn ($y) => ['value' => sprintf('%04d-01-01', $y), 'label' => (string) $y],
                    range($endYear, $startYear)
                ),
                'order' => 2,
                'is_entry_point' => true,
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => 'age_lt', 'value' => 18], 'next' => 'queue:consent_declined_whatsapp,consent_declined_commune,consent_declined_end'],
                    ['if' => ['source' => 'answer', 'operator' => 'age_gte', 'value' => 18], 'next' => 'question:commune'],
                ],
            ],
            [
                'key' => 'commune',
                'text' => 'Dans quelle commune habitez-vous ?',
                'type' => 'select_one',
                'bloc_key' => 'profil_identite',
                'answer_target' => 'personal_profile',
                'options' => $communeOptions,
                'order' => 3,
            ],
            [
                'key' => 'cmu',
                'text' => 'Bénéficiez-vous de la Couverture Maladie Universelle (CMU) ?',
                'type' => 'boolean',
                'bloc_key' => 'profil_identite',
                'answer_target' => 'personal_profile',
                'order' => 4,
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => true], 'next' => 'question:cmu_number'],
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => false], 'next' => 'question:marital_status'],
                ],
            ],
            [
                'key' => 'cmu_number',
                'text' => 'Quel est votre numÃ©ro de CMU ?',
                'type' => 'text',
                'bloc_key' => 'profil_identite',
                'answer_target' => 'medical_profile',
                'order' => 5,
                'visible_if' => json_encode([
                    ['source' => 'question', 'key' => 'cmu', 'operator' => '=', 'value' => true],
                ]),
            ],
            [
                'key' => 'marital_status',
                'text' => 'Quel est votre statut matrimonial ?',
                'type' => 'select_one',
                'bloc_key' => 'profil_identite',
                'answer_target' => 'personal_profile',
                'options' => ['Célibataire', 'Marié(e)', 'Divorcé(e)', 'Veuf(ve)'],
                'order' => 6,
            ],
            [
                'key' => 'education_level',
                'text' => 'Quel est votre niveau d\'éducation ?',
                'type' => 'select_one',
                'bloc_key' => 'profil_identite',
                'answer_target' => 'personal_profile',
                'options' => ['Aucun', 'Primaire', 'Secondaire', 'Supérieur', 'Autodidacte'],
                'order' => 7,
            ],
            [
                'key' => 'occupation',
                'text' => 'Quelle est votre profession ou activité principale actuelle ?',
                'type' => 'text',
                'bloc_key' => 'profil_identite',
                'answer_target' => 'personal_profile',
                'order' => 8,
                'is_required' => true,
                'placeholder' => 'Ex: Étudiante, commerçante, sans emploi...',
                'routing_rules' => [
                    ['if' => true, 'next' => 'queue:tabac,alcool,activite_physique,stress_chronique,poids_kg,taille_cm'],
                ],
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
