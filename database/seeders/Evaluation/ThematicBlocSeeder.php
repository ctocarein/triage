<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use App\Domains\Evaluation\Models\ThematicBloc;
use App\Domains\Evaluation\Models\QuestionGroup;

class ThematicBlocSeeder extends Seeder
{
    public function run(): void
    {
        // Pour chaque bloc, on lie à un groupe (par sa clé)
        $blocs = [
            [
                'key' => 'symptom_breast',
                'label' => 'Douleurs mammaires',
                'color' => '#FFB74D',
                'icon'  => 'alert-triangle',
                'description' => 'Questions sur les douleurs mammaires.',
                'order' => 1,
                'question_group_key' => 'symptom_breast', // clé du groupe cible
            ],
            [
                'key' => 'symptom_breast_lump',
                'label' => 'Masse palpable',
                'color' => '#4CAF50',
                'icon'  => 'circle',
                'description' => 'Questions sur la palpation.',
                'order' => 2,
                'question_group_key' => 'symptom_breast_lump',
            ],
            [
                'key' => 'symptom_cervix',
                'label' => 'Douleurs cervicales',
                'color' => '#FFB74D',
                'icon'  => 'alert-triangle',
                'description' => 'Questions sur les douleurs au col de l’utérus.',
                'order' => 3,
                'question_group_key' => 'symptom_cervix',
            ],
            [
                'key' => 'lifestyle',
                'label' => 'Mode de vie',
                'color' => '#4CAF50',
                'icon'  => 'activity',
                'description' => 'Questions sur le mode de vie.',
                'order' => 4,
                'question_group_key' => 'lifestyle',
            ],
            [
                'key' => 'family_history',
                'label' => 'ATCD familiaux',
                'color' => '#90CAF9',
                'icon'  => 'users',
                'description' => 'Questions sur les antécédents familiaux.',
                'order' => 5,
                'question_group_key' => 'profile_personal',
            ],
            // ... Ajoute d’autres blocs selon besoins ...
        ];

        foreach ($blocs as $bloc) {
            // On récupère l'ID du groupe par sa clé
            $groupId = QuestionGroup::where('key', $bloc['question_group_key'])->value('id');
            if (!$groupId) {
                // Log ou skip si le groupe n'existe pas
                continue;
            }

            ThematicBloc::updateOrCreate(
                ['key' => $bloc['key']],
                [
                    'label' => $bloc['label'],
                    'color' => $bloc['color'],
                    'icon' => $bloc['icon'],
                    'description' => $bloc['description'],
                    'order' => $bloc['order'],
                    'is_active' => true,
                    'question_group_id' => $groupId,
                ]
            );
        }
    }
}
