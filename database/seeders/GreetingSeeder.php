<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Services\Catalog\QuestionTemplateContractValidator;

class GreetingSeeder extends Seeder
{
    public function run(): void
    {
        $validator = new QuestionTemplateContractValidator();

        $questions = [
            // ----------------------------------------------------------------
            // 1 — Message de bienvenue (informatif, pas de réponse)
            // ----------------------------------------------------------------
            [
                'key'            => 'greeting_welcome',
                'text'           => 'Bienvenue sur CHEREH, votre outil de santé numérique conçu pour vous aider à repérer plus tôt certains signes de risque liés aux cancers féminins.',
                'type'           => 'custom',
                'bloc_key'       => 'greeting',
                'answer_target'  => null,
                'is_entry_point' => true,
                'order'          => 1,
            ],

            // ----------------------------------------------------------------
            // 2 — Notice de confidentialité (informatif, pas de réponse)
            // ----------------------------------------------------------------
            [
                'key'            => 'greeting_confidential',
                'text'           => "Information importante.\n\nCHEREH ne pose pas de diagnostic médical et ne remplace pas un professionnel de santé.\n\nLes résultats fournis servent uniquement à vous orienter.\n\nVos réponses resteront strictement confidentielles et sécurisées.",
                'type'           => 'custom',
                'bloc_key'       => 'greeting',
                'answer_target'  => null,
                'is_entry_point' => false,
                'order'          => 2,
            ],

            // ----------------------------------------------------------------
            // 3 — Consentement explicite
            // Refus → bloc consent_declined
            // Accepte → question suivante du parcours
            // ----------------------------------------------------------------
            [
                'key'           => 'explicit_consent',
                'text'          => 'Acceptez-vous de répondre à ces questions afin de recevoir une orientation médicale personnalisée ?',
                'type'          => 'select_one',
                'bloc_key'      => 'greeting',
                'answer_target' => 'consent',
                'order'         => 3,
                'is_required'   => true,
                'options'       => json_encode([
                    ['value' => 'yes', 'label' => "Oui, j'accepte"],
                    ['value' => 'no',  'label' => 'Non, je ne souhaite pas continuer'],
                ]),
                'routing_rules' => [

                    [
                    'if' => ['source' => 'answer', 'operator' => '=', 'value' => 'no'], 
                    'next' => 'question:consent_declined_thank_you'
                    ],
                    [
                        'if' => ['source' => 'answer', 'operator' => '=', 'value' => 'yes'], 
                        'next' => 'question:raison_visite'
                    ],
                ],
            ],

            // ----------------------------------------------------------------
            // 4 — Raison de la visite (multiselect)
            // On capture l'intention, puis on envoie vers le profil avant
            // d'activer les blocs symptomatiques dépendants de l'âge.
            // ----------------------------------------------------------------
            [
                'key'           => 'raison_visite',
                'text'          => 'Qu\'est-ce qui vous amène aujourd\'hui ?',
                'type'          => 'select_multiple',
                'bloc_key'      => 'greeting',
                'answer_target' => 'evaluation',
                'order'         => 4,
                'is_required'   => true,
                'options'       => json_encode([
                    ['value' => 'depistage', 'label' => 'Je viens pour un dépistage de routine'],
                    ['value' => 'sein',      'label' => 'je veux comprendre mes symptômes mammaires'],
                    ['value' => 'cervical',  'label' => 'je veux comprendre mes symptômes gynécologiques'],
                ]),
                'routing_rules' => [
                    [
                        'if'   => ['source' => 'answer', 'operator' => 'contains', 'value' => 'sein'],
                        'next' => 'bloc:profile_identity',
                    ],
                    [
                        'if'   => ['source' => 'answer', 'operator' => 'contains', 'value' => 'cervical'],
                        'next' => 'bloc:profile_identity',
                    ],
                    [
                        'if'   => ['source' => 'answer', 'operator' => 'contains', 'value' => 'depistage'],
                        'next' => 'bloc:profile_identity',
                    ],
                ],
            ],
        ];

        $validator->assertUniqueOrderPerBloc($questions, static::class);

        foreach ($questions as $q) {
            $q = $validator->normalize($q);
            $validator->assertValid($q);
            DynamicQuestionTemplate::updateOrCreate(['key' => $q['key']], $q);
        }
    }
}
