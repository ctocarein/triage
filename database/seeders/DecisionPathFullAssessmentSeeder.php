<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Decisions\DecisionNode;
use App\Models\Decisions\DecisionPath;
use App\Models\Evaluation\DynamicQuestionTemplate;

class DecisionPathFullAssessmentSeeder extends Seeder
{
    public function run()
    {
        // Création/Màj du DecisionPath
        $path = DecisionPath::updateOrCreate([
            'key' => 'dp_full_assessment',
        ], [
            'title' => 'Évaluation complète CHEREH',
            'description' => 'Parcours global intégrant identité, risques, symptômes mammaires, gynécologiques, habitudes de vie...',
            'category' => 'complete',
            'source_guideline' => 'OMS, HAS, INCa 2024',
            'version' => '1.0',
            'is_active' => true,
        ]);

        // 1️⃣ Liste des clés de questions attendues
        $questionKeys = [
            'first_name',
            'birth_date',
            'commune',
            'cmu',
            // 'family_cancer_history',
            'breast_pain',
            'breast_mass',
            'vaginal_bleeding_abnormal',
            // 'exposed_to_smoke'
        ];

        // 2️⃣ Récupération de toutes les questions attendues en une seule requête
        $questions = DynamicQuestionTemplate::whereIn('key', $questionKeys)->get()->keyBy('key');

        // 3️⃣ Vérification des clés manquantes
        $missing = array_diff($questionKeys, $questions->keys()->toArray());
        if (count($missing)) {
            throw new \Exception("Les clés de questions suivantes sont manquantes dans dynamic_question_templates: " . implode(', ', $missing));
        }

        // 4️⃣ Affectation aux variables
        $q_firstname         = $questions['first_name'];
        $q_birthdate         = $questions['birth_date'];
        $q_commune           = $questions['commune'];
        $q_cmu               = $questions['cmu'];
        // $q_family_history    = $questions['family_cancer_history'];
        $q_breast_pain       = $questions['breast_pain'];
        $q_breast_mass       = $questions['breast_mass'];
        $q_gyneco_bleeding   = $questions['vaginal_bleeding_abnormal'];
        // $q_lifestyle         = $questions['exposed_to_smoke'];

        // --- Bloc Identité
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_first_name',
        ], [
            'dynamic_question_template_id' => $q_firstname->id,
            'order' => 1,
            'branches' => json_encode(['default' => 'node_birth_date']),
            'node_type' => 'question',
        ]);
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_birth_date',
        ], [
            'dynamic_question_template_id' => $q_birthdate->id,
            'order' => 2,
            'branches' => json_encode(['default' => 'node_commune']),
            'node_type' => 'question',
        ]);
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_commune',
        ], [
            'dynamic_question_template_id' => $q_commune->id,
            'order' => 3,
            'branches' => json_encode(['default' => 'node_cmu']),
            'node_type' => 'question',
        ]);
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_cmu',
        ], [
            'dynamic_question_template_id' => $q_cmu->id,
            'order' => 4,
            'branches' => json_encode(['default' => 'node_family_history']),
            'node_type' => 'question',
        ]);
        // Bloc Risque familial
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_family_history',
        ], [
            'dynamic_question_template_id' => null, // Pas de question ici, on utilise un nœud de type "question"
            'order' => 5,
            'branches' => json_encode([
                true  => 'node_end_risk',    // Si ATCD familiaux => haut risque
                false => 'node_breast_pain',
                'default' => 'node_breast_pain'
            ]),
            'node_type' => 'question',
        ]);
        // Bloc Symptômes mammaires
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_breast_pain',
        ], [
            'dynamic_question_template_id' => $q_breast_pain->id,
            'order' => 6,
            'branches' => json_encode([
                true  => 'node_breast_mass',
                false => 'node_gyneco_bleeding',
                'default' => 'node_gyneco_bleeding'
            ]),
            'node_type' => 'question',
        ]);
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_breast_mass',
        ], [
            'dynamic_question_template_id' => $q_breast_mass->id,
            'order' => 7,
            'branches' => json_encode([
                true  => 'node_end_risk', // Masse mammaire = risque élevé
                false => 'node_gyneco_bleeding',
                'default' => 'node_gyneco_bleeding'
            ]),
            'node_type' => 'question',
        ]);
        // Bloc Symptômes gynéco
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_gyneco_bleeding',
        ], [
            'dynamic_question_template_id' => $q_gyneco_bleeding->id,
            'order' => 8,
            'branches' => json_encode([
                true  => 'node_end_checkup',
                false => 'node_lifestyle',
                'default' => 'node_lifestyle'
            ]),
            'node_type' => 'question',
        ]);
        // Bloc Habitudes de vie
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_lifestyle',
        ], [
            'dynamic_question_template_id' => null, // Pas de question ici, on utilise un nœud de type "question"
            'order' => 9,
            'branches' => json_encode(['default' => 'node_end_normal']),
            'node_type' => 'question',
        ]);
        // Nœuds de fin
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_end_risk',
        ], [
            'dynamic_question_template_id' => null,
            'order' => 100,
            'branches' => null,
            'node_type' => 'end',
            'outcome_message' => "Risque élevé ou masse identifiée. Consultez un spécialiste rapidement.",
            'risk_weight' => 20,
            'specialties' => json_encode(['gynécologie', 'oncologie']),
        ]);
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_end_checkup',
        ], [
            'dynamic_question_template_id' => null,
            'order' => 101,
            'branches' => null,
            'node_type' => 'end',
            'outcome_message' => "Symptôme gynécologique détecté. Consultez un centre de santé pour un contrôle.",
            'risk_weight' => 12,
            'specialties' => json_encode(['gynécologie']),
        ]);
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_end_normal',
        ], [
            'dynamic_question_template_id' => null,
            'order' => 102,
            'branches' => null,
            'node_type' => 'end',
            'outcome_message' => "Aucun risque significatif. Restez vigilante et continuez la surveillance.",
            'risk_weight' => 0,
            'specialties' => null,
        ]);
    }
}
