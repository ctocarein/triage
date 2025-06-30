<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DecisionPathFullAssessmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // 1️⃣ — Crée ou met à jour le DecisionPath complet
        $path = DecisionPath::updateOrCreate([
            'key' => 'dp_full_assessment',
        ], [
            'title' => 'Évaluation complète CHEREH',
            'description' => 'Parcours global intégrant tous les principaux blocs thématiques (identité, habitudes, symptômes mammaires/gynéco, facteurs de risque…)',
            'category' => 'complete',
            'source_guideline' => 'OMS, HAS 2023',
            'version' => '1.0',
            'is_active' => true,
        ]);

        // 2️⃣ — Prépare les questions dynamiques utilisées dans le flow
        $q_identity       = DynamicQuestionTemplate::where('key', 'first_name')->firstOrFail();
        $q_age            = DynamicQuestionTemplate::where('key', 'birth_date')->firstOrFail();
        $q_cmu            = DynamicQuestionTemplate::where('key', 'cmu')->firstOrFail();
        $q_breast_pain    = DynamicQuestionTemplate::where('key', 'breast_pain')->firstOrFail();
        $q_bleeding       = DynamicQuestionTemplate::where('key', 'cervical_bleeding')->firstOrFail();
        $q_lifestyle      = DynamicQuestionTemplate::where('key', 'smoking')->firstOrFail();
        $q_family_history = DynamicQuestionTemplate::where('key', 'family_history_cancer')->firstOrFail();

        // 3️⃣ — Entrée : Profil identité (bloc 1)
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_identity_name',
        ], [
            'dynamic_question_template_id' => $q_identity->id,
            'order' => 1,
            'branches' => json_encode(['default' => 'node_identity_age']),
            'node_type' => 'question',
        ]);

        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_identity_age',
        ], [
            'dynamic_question_template_id' => $q_age->id,
            'order' => 2,
            'branches' => json_encode(['default' => 'node_cmu']),
            'node_type' => 'question',
        ]);

        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_cmu',
        ], [
            'dynamic_question_template_id' => $q_cmu->id,
            'order' => 3,
            'branches' => json_encode(['default' => 'node_breast_pain']),
            'node_type' => 'question',
        ]);

        // 4️⃣ — Bloc symptômes mammaires (bloc 2)
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_breast_pain',
        ], [
            'dynamic_question_template_id' => $q_breast_pain->id,
            'order' => 4,
            'branches' => json_encode([
                'oui' => 'node_family_history',
                'non' => 'node_bleeding',
                'default' => 'node_bleeding'
            ]),
            'node_type' => 'question',
        ]);

        // 5️⃣ — Bloc facteurs de risque familiaux (ramifié)
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_family_history',
        ], [
            'dynamic_question_template_id' => $q_family_history->id,
            'order' => 5,
            'branches' => json_encode([
                'oui' => 'node_end_risk',
                'non' => 'node_bleeding',
                'default' => 'node_bleeding'
            ]),
            'node_type' => 'question',
        ]);

        // 6️⃣ — Bloc symptômes gynécologiques (bloc 3)
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_bleeding',
        ], [
            'dynamic_question_template_id' => $q_bleeding->id,
            'order' => 6,
            'branches' => json_encode([
                'oui' => 'node_end_checkup',
                'non' => 'node_lifestyle',
                'default' => 'node_lifestyle'
            ]),
            'node_type' => 'question',
        ]);

        // 7️⃣ — Bloc habitudes de vie (bloc 4)
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_lifestyle',
        ], [
            'dynamic_question_template_id' => $q_lifestyle->id,
            'order' => 7,
            'branches' => json_encode(['default' => 'node_end_normal']),
            'node_type' => 'question',
        ]);

        // 8️⃣ — Nœuds de fin/issue
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_end_risk',
        ], [
            'dynamic_question_template_id' => null,
            'order' => 20,
            'branches' => null,
            'node_type' => 'end',
            'outcome_message' => "Risque familial élevé identifié. Consultez un spécialiste sans tarder.",
            'risk_weight' => 15,
            'specialties' => json_encode(['génétique', 'gynécologie']),
        ]);

        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_end_checkup',
        ], [
            'dynamic_question_template_id' => null,
            'order' => 21,
            'branches' => null,
            'node_type' => 'end',
            'outcome_message' => "Anomalie gynécologique détectée. Un contrôle médical est conseillé.",
            'risk_weight' => 10,
            'specialties' => json_encode(['gynécologie']),
        ]);

        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_end_normal',
        ], [
            'dynamic_question_template_id' => null,
            'order' => 22,
            'branches' => null,
            'node_type' => 'end',
            'outcome_message' => "Aucun risque significatif détecté. Continuez la surveillance régulière.",
            'risk_weight' => 0,
            'specialties' => null,
        ]);
    }
}
