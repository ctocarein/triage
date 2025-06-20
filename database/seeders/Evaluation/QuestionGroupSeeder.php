<?php

namespace Database\Seeders\Evaluation;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionGroupSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $groups = [
            // -------- GROUPES PRINCIPAUX ---------
            [
                'key' => 'profile_personal',
                'label' => 'Profil personnel',
                'parent_key' => null,
                'description' => 'Informations sur votre identité et situation personnelle.',
                'icon' => 'user',
                'color' => '#F8A734',
                'order' => 1,
                'conditions' => null,
                'is_active' => true,
            ],
            [
                'key' => 'symptom_breast',
                'label' => 'Symptômes mammaires',
                'parent_key' => null,
                'description' => 'Douleurs, masse, écoulement...',
                'icon' => 'activity',
                'color' => '#C24E3A',
                'order' => 2,
                'conditions' => null,
                'is_active' => true,
            ],
            [
                'key' => 'symptom_cervix',
                'label' => 'Symptômes Col de l’utérus',
                'parent_key' => null,
                'description' => 'Douleurs, écoulement...',
                'icon' => 'activity',
                'color' => '#C24E3A',
                'order' => 2,
                'conditions' => null,
                'is_active' => true,
            ],
            // -------- SOUS-GROUPES PROFIL ---------
            [
                'key' => 'profil_identite',
                'label' => 'Identité',
                'parent_key' => 'profile_personal',
                'description' => 'Nom, prénom, date de naissance...',
                'icon' => 'user-check',
                'color' => '#FFD580',
                'order' => 1,
                'conditions' => null,
                'is_active' => true,
            ],
            [
                'key' => 'profil_socio',
                'label' => 'Situation socio-économique',
                'parent_key' => 'profile_personal',
                'description' => 'Commune, éducation, profession, CMU...',
                'icon' => 'users',
                'color' => '#FFE4B5',
                'order' => 2,
                'conditions' => null,
                'is_active' => true,
            ],
            [
                'key' => 'profil_sante',
                'label' => 'Paramètres santé',
                'parent_key' => 'profile_personal',
                'description' => 'Taille, poids, etc.',
                'icon' => 'activity',
                'color' => '#B2F7EF',
                'order' => 3,
                'conditions' => null,
                'is_active' => true,
            ],
            [
                'key' => 'profil_repro',
                'label' => 'Santé reproductive',
                'parent_key' => 'profile_personal',
                'description' => 'Antécédents gynécologiques, contraception...',
                'icon' => 'heart',
                'color' => '#FFCDD2',
                'order' => 4,
                'conditions' => null,
                'is_active' => true,
            ],
            [
                'key' => 'profil_familial',
                'label' => 'Antécédents familiaux',
                'parent_key' => 'profile_personal',
                'description' => 'Antécédents médicaux familiaux.',
                'icon' => 'users',
                'color' => '#E1BEE7',
                'order' => 5,
                'conditions' => null,
                'is_active' => true,
            ],
            // -------- SOUS-GROUPES SYMPTÔMES MAMMAIRES ---------
            [
                'key' => 'symptom_breast_pain',
                'label' => 'Douleurs mammaires',
                'parent_key' => 'symptom_breast',
                'description' => 'Questions sur les douleurs mammaires.',
                'icon' => 'alert-triangle',
                'color' => '#FFB74D',
                'order' => 1,
                'conditions' => null,
                'is_active' => true,
            ],
            [
                'key' => 'symptom_breast_lump',
                'label' => 'Masse palpable',
                'parent_key' => 'symptom_breast',
                'description' => 'Questions sur la palpation.',
                'icon' => 'circle',
                'color' => '#4CAF50',
                'order' => 2,
                'conditions' => null,
                'is_active' => true,
            ],
            
          
            // -------- SOUS-GROUPES SYMPTÔMES COL DE L'UTERUS ---------
            [
                'key' => 'symptom_cervix_pain',
                'label' => 'Douleurs cervicales',
                'parent_key' => 'symptom_cervix',
                'description' => 'Questions sur les douleurs au niveau du col de l’utérus.',
                'icon' => 'alert-triangle',
                'color' => '#FFB74D',
                'order' => 1,
                'conditions' => null,
                'is_active' => true,
            ],
            [
                'key' => 'symptom_cervix_discharge',
                'label' => 'Écoulement vaginal',
                'parent_key' => 'symptom_cervix',
                'description' => 'Questions sur les écoulements vaginaux.',
                'icon' => 'droplet',
                'color' => '#4CAF50',
                'order' => 2,
                'conditions' => null,
                'is_active' => true,
            ],
            [
                'key' => 'symptom_cervix_irregularities',
                'label' => 'Irrégularités menstruelles',
                'parent_key' => 'symptom_cervix',
                'description' => 'Questions sur les irrégularités du cycle menstruel.',
                'icon' => 'calendar',
                'color' => '#FF9800',
                'order' => 3,
                'conditions' => null,
                'is_active' => true,
            ]
            // ...Ajoute d’autres groupes/sous-groupes ici selon tes besoins...
        ];

        foreach ($groups as $group) {
            DB::table('question_groups')->updateOrInsert(
                ['key' => $group['key']],
                array_merge($group, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );
        }
    }
}
