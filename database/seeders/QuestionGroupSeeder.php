<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class QuestionGroupSeeder extends Seeder
{
    public function run()
    {
        $now = Carbon::now();

        DB::table('question_groups')->insert([
            [
                'key' => 'identite',
                'label' => 'Identité',
                'icon' => 'user',
                'color' => '#4CAF50',
                'description' => 'Informations de base de la femme',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'socio',
                'label' => 'Situation sociale',
                'icon' => 'users',
                'color' => '#009688',
                'description' => 'Niveau d’éducation, langue, commune…',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'maternite',
                'label' => 'Maternité / Statut hormonal',
                'icon' => 'baby',
                'color' => '#FF9800',
                'description' => 'Grossesse, allaitement, ménopause',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'symptomes_sein',
                'label' => 'Symptômes du sein',
                'icon' => 'stethoscope',
                'color' => '#E91E63',
                'description' => 'Masses, douleurs, écoulement mammaire…',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'symptomes_col',
                'label' => 'Symptômes du col de l’utérus',
                'icon' => 'venus',
                'color' => '#9C27B0',
                'description' => 'Saignements, douleurs pelviennes…',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'suivi',
                'label' => 'Suivi post-dépistage',
                'icon' => 'clipboard-check',
                'color' => '#607D8B',
                'description' => 'Questions après la recommandation',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'rappel_prevention',
                'label' => 'Rappels prévention',
                'icon' => 'calendar-check',
                'color' => '#3F51B5',
                'description' => 'Autopalpation, consultation annuelle',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
