<?php

namespace Database\Seeders;

use App\Domains\Recommendation\Models\Capsule;
use App\Domains\Recommendation\Models\CapsuleItem;
use Illuminate\Database\Seeder;

class CapsuleSeeder extends Seeder
{
    public function run(): void
    {
        $capsule = Capsule::updateOrCreate(
            ['slug' => 'auto-palpation-guide'],
            [
                'title' => "Comment faire l'auto-palpation ?",
                'description' => 'Apprends les gestes simples pour detecter les anomalies mammaires.',
                'target_age_min' => 18,
                'target_age_max' => 60,
                'audience_profile' => 'cycle_regular',
                'language' => 'fr',
                'is_active' => true,
                'published_at' => now(),
            ]
        );

        $items = [
            [
                'position' => 1,
                'type' => 'text',
                'content_text' => 'Bonjour ! Voici une capsule pour apprendre a surveiller ta sante mammaire.',
                'duration_seconds' => 6,
            ],
            [
                'position' => 2,
                'type' => 'image',
                'content_text' => 'Observe la forme de tes seins dans un miroir, bras leves.',
                'media_url' => 'https://cdn.chereh.app/assets/palpation1.png',
                'duration_seconds' => 7,
            ],
            [
                'position' => 3,
                'type' => 'quiz',
                'content_text' => "Test : Quand faut-il faire une auto-palpation ?",
                'quiz_data' => [
                    'question' => "Quelle est la meilleure periode pour l'auto-palpation ?",
                    'options' => [
                        'Pendant les regles',
                        '3 a 5 jours apres les regles',
                        'Juste avant les regles',
                    ],
                    'correct_answer' => '3 a 5 jours apres les regles',
                    'explanation' => "C'est a ce moment que les seins sont les moins sensibles.",
                ],
                'duration_seconds' => 15,
            ],
            [
                'position' => 4,
                'type' => 'text',
                'content_text' => 'En cas de doute, prends rendez-vous avec ta sage-femme.',
                'cta_label' => 'Prendre RDV',
                'cta_link' => 'https://chereh.app/rdv',
                'duration_seconds' => 6,
            ],
            [
                'position' => 5,
                'type' => 'image',
                'content_text' => 'Campagne soutenue par FemTech Africa',
                'media_url' => 'https://cdn.chereh.app/sponsor/logo_femtech.png',
                'sponsored_content' => true,
                'sponsor_message' => 'FemTech finance la prevention feminine.',
                'sponsored_link_type' => 'product',
                'sponsored_action_value' => 'https://femtech.africa/auto-palpation-kit',
                'duration_seconds' => 6,
            ],
        ];

        foreach ($items as $item) {
            CapsuleItem::updateOrCreate(
                [
                    'capsule_id' => $capsule->id,
                    'position' => $item['position'],
                ],
                $item
            );
        }
    }
}
