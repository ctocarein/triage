<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'key' => 'profile_personal',
                'label' => 'Profil personnel',
                'description' => 'Informations de base sur votre identité.',
                'icon' => 'user',
                'color' => '#F8A734',
                'order' => 1,
                'conditions' => null,
            ],
            [
                'key' => 'profile_medical',
                'label' => 'Profil médical',
                'description' => 'Informations sur vos antécédents médicaux.',
                'icon' => 'stethoscope',
                'color' => '#F8DC82',
                'order' => 2,
                'conditions' => null,
            ],
            [
                'key' => 'symptom_breast',
                'label' => 'Symptômes mammaires',
                'description' => 'Symptômes liés au sein : douleurs, masse, écoulements...',
                'icon' => 'activity',
                'color' => '#C24E3A',
                'order' => 3,
                    'conditions' => json_encode([
                    [
                        'source' => 'profile.personal',
                        'key' => 'age',
                        'operator' => '>=',
                        'value' => 24
                    ]
                ]),
            ],
            [
                'key' => 'symptom_cervix',
                'label' => 'Symptômes gynécologiques',
                'description' => 'Saignements, douleurs pelviennes ou anomalies gynécologiques.',
                'icon' => 'droplet',
                'color' => '#F8EBC5',
                'order' => 4,
                'conditions' => json_encode([
                    [
                        'source' => 'profile.personal',
                        'key' => 'age',
                        'operator' => '>=',
                        'value' => 18
                    ]
                ]),
            ],
            [
                'key' => 'lifestyle',
                'label' => 'Habitudes de vie',
                'description' => 'Habitudes alimentaires, activité physique, tabac, alcool.',
                'icon' => 'heart',
                'color' => '#6C757D',
                'order' => 5,
                'conditions' => null,
            ],
            [
                'key' => 'final_recommendation',
                'label' => 'Résumé et recommandations',
                'description' => 'Conclusion basée sur vos réponses.',
                'icon' => 'check-circle',
                'color' => '#162447',
                'order' => 6,
                'conditions' => null,
            ],
        ];

        foreach ($groups as $group) {
            DB::table('question_groups')->updateOrInsert(
                ['key' => $group['key']],
                array_merge($group, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
