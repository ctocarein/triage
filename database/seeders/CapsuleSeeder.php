<?php

namespace Database\Seeders;

use App\Domains\Recommendation\Models\Capsule;
use Illuminate\Database\Seeder;
use App\Domains\Recommendation\Models\CapsuleItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class CapsuleSeeder extends Seeder
{
    public function run(): void
    {
        $capsule = Capsule::create([
            'title' => 'Comment faire l’auto-palpation ?',
            'slug' => 'auto-palpation-guide',
            'description' => 'Apprends les gestes simples pour détecter les anomalies mammaires.',
            'target_age_min' => 18,
            'target_age_max' => 60,
            'audience_profile' => 'cycle_regular',
            'language' => 'fr',
            'is_active' => true,
            'published_at' => now(),
        ]);

       CapsuleItem::create([
    'capsule_id' => $capsule->id,
    'position' => 1,
    'type' => 'text',
    'content_text' => 'Bonjour ! Voici une capsule pour apprendre à surveiller ta santé mammaire.',
    'duration_seconds' => 6,
]);

CapsuleItem::create([
    'capsule_id' => $capsule->id,
    'position' => 2,
    'type' => 'image',
    'content_text' => 'Observe la forme de tes seins dans un miroir, bras levés.',
    'media_url' => 'https://cdn.chereh.app/assets/palpation1.png',
    'duration_seconds' => 7,
]);

CapsuleItem::create([
    'capsule_id' => $capsule->id,
    'position' => 3,
    'type' => 'quiz',
    'content_text' => 'Test : Quand faut-il faire une auto-palpation ?',
    'quiz_data' => [
        'question' => 'Quelle est la meilleure période pour l’auto-palpation ?',
        'options' => [
            'Pendant les règles',
            '3 à 5 jours après les règles',
            'Juste avant les règles'
        ],
        'correct_answer' => '3 à 5 jours après les règles',
        'explanation' => 'C’est à ce moment que les seins sont les moins sensibles.'
    ],
    'duration_seconds' => 15,
]);

CapsuleItem::create([
    'capsule_id' => $capsule->id,
    'position' => 4,
    'type' => 'text',
    'content_text' => 'En cas de doute, prends rendez-vous avec ta sage-femme.',
    'cta_label' => 'Prendre RDV',
    'cta_link' => 'https://chereh.app/rdv',
    'duration_seconds' => 6,
]);

CapsuleItem::create([
    'capsule_id' => $capsule->id,
    'position' => 5,
    'type' => 'image',
    'content_text' => 'Campagne soutenue par FemTech Africa',
    'media_url' => 'https://cdn.chereh.app/sponsor/logo_femtech.png',
    'sponsored_content' => true,
    'sponsor_message' => 'FemTech finance la prévention féminine.',
    'sponsored_link_type' => 'product',
    'sponsored_action_value' => 'https://femtech.africa/auto-palpation-kit',
    'duration_seconds' => 6,
]);

    }
}
