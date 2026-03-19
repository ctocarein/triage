<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;

class ConsentDeclinedQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();

        $communeOptions = DB::table('communes')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => ['value' => $row->name, 'label' => $row->name])
            ->toArray();

        $startYear = (int) date('Y') - 90;
        $endYear = (int) date('Y') - 18;

        $questions = [
            [
                'key' => 'consent_declined_thank_you',
                'text' => 'Merci pour votre retour.',
                'type' => 'custom',
                'bloc_key' => 'consent_declined',
                'answer_target' => 'custom',
                'options' => [
                    ['value' => 'continue', 'label' => 'Je vous en prie'],
                ],
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => 'continue'], 'next' => 'question:consent_declined_year'],
                ],
                'is_required' => true,
                'is_entry_point' => true,
                'order' => 1,
            ],
            [
                'key' => 'consent_declined_whatsapp',
                'text' => 'Si vous le souhaitez, laissez votre numero WhatsApp pour recevoir nos notifications.',
                'type' => 'text',
                'bloc_key' => 'consent_declined',
                'answer_target' => 'custom',
                'is_required' => false,
                'placeholder' => 'Ex: +225 0700000000',
                'routing_rules' => [
                    ['if' => true, 'next' => 'question:consent_declined_end'],
                ],
                'order' => 2,
            ],
            [
                'key' => 'consent_declined_year',
                'text' => 'Puis-je avoir votre annee de naissance ?',
                'type' => 'select_one',
                'options' => array_map(fn($y) => ['value' => (string) $y, 'label' => (string) $y], range($endYear, $startYear)),
                'bloc_key' => 'consent_declined',
                'answer_target' => 'custom',
                'is_required' => false,
                'routing_rules' => [
                    ['if' => true, 'next' => 'question:consent_declined_commune'],
                ],
                'order' => 3,
            ],
            [
                'key' => 'consent_declined_commune',
                'text' => 'Votre commune de residence ?',
                'type' => 'select_one',
                'bloc_key' => 'consent_declined',
                'answer_target' => 'custom',
                'options' => $communeOptions,
                'is_required' => false,
                'routing_rules' => [
                    ['if' => true, 'next' => 'question:consent_declined_whatsapp'],
                ],
                'order' => 4,
            ],
            [
                'key' => 'consent_declined_end',
                'text' => 'Aucune recommandation ne sera chargee pour ce parcours. Appuyez sur Terminer pour quitter.',
                'type' => 'custom',
                'bloc_key' => 'consent_declined',
                'answer_target' => 'custom',
                'options' => [
                    ['value' => 'done', 'label' => 'Terminer'],
                ],
                'routing_rules' => [
                    ['if' => ['source' => 'answer', 'operator' => '=', 'value' => 'done'], 'next' => 'end_session:consent_declined'],
                ],
                'is_required' => true,
                'order' => 5,
            ],
        ];

        $validator->assertUniqueOrderPerBloc($questions, static::class);

        foreach ($questions as $q) {
            $q = $validator->normalize($q);
            $validator->assertValid($q);

            DynamicQuestionTemplate::updateOrCreate(
                ['key' => $q['key']],
                $q
            );
        }
    }
}
