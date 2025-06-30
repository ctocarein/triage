<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Decisions\DecisionNode;
use App\Models\Decisions\DecisionPath;
use App\Models\Evaluation\DynamicQuestionTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DecisionPathBreastPainSeeder extends Seeder
{
     public function run()
    {
        // 1️⃣ — Crée ou met à jour le DecisionPath principal
        $path = DecisionPath::updateOrCreate([
            'key' => 'dp_breast_pain',
        ], [
            'title' => 'Parcours douleur mammaire',
            'description' => 'Parcours décisionnel pour les douleurs au sein',
            'category' => 'symptôme',
            'source_guideline' => 'HAS 2022',
            'version' => '1.0',
            'is_active' => true,
        ]);

        // 2️⃣ — Prépare tes questions dynamiques (doivent déjà exister en base !)
        $q_breast_pain       = DynamicQuestionTemplate::where('key', 'breast_pain')->firstOrFail();
        // $q_pain_duration     = DynamicQuestionTemplate::where('key', 'breast_pain_duration')->firstOrFail();
        // $q_family_history    = DynamicQuestionTemplate::where('key', 'family_history_cancer')->firstOrFail();

        // 3️⃣ — Les nodes
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_start',
        ], [
            'dynamic_question_template_id' => $q_breast_pain->id,
            'order' => 1,
            'branches' => json_encode([
                'oui' => 'node_duration',
                'non' => 'node_end_normal',
            ]),
            'node_type' => 'question',
        ]);

        // DecisionNode::updateOrCreate([
        //     'decision_path_id' => $path->id,
        //     'node_key' => 'node_duration',
        // ], [
        //     'dynamic_question_template_id' => $q_pain_duration->id,
        //     'order' => 2,
        //     'branches' => json_encode([
        //         '>=7' => 'node_family_history', // Si la douleur dure 7 jours ou plus
        //         '<7'  => 'node_end_normal',
        //     ]),
        //     'node_type' => 'question',
        // ]);

        // DecisionNode::updateOrCreate([
        //     'decision_path_id' => $path->id,
        //     'node_key' => 'node_family_history',
        // ], [
        //     'dynamic_question_template_id' => $q_family_history->id,
        //     'order' => 3,
        //     'branches' => json_encode([
        //         'oui' => 'node_end_risk',
        //         'non' => 'node_end_checkup',
        //     ]),
        //     'node_type' => 'question',
        // ]);

        // Nœud de fin : normal
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_end_normal',
        ], [
            'dynamic_question_template_id' => null,
            'order' => 10,
            'branches' => null,
            'node_type' => 'end',
            'outcome_message' => "Aucune douleur significative. Continuez la surveillance régulière.",
            'risk_weight' => 0,
        ]);

        // Nœud de fin : risque élevé
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_end_risk',
        ], [
            'dynamic_question_template_id' => null,
            'order' => 11,
            'branches' => null,
            'node_type' => 'end',
            'outcome_message' => "Risque élevé : consultez un spécialiste sans tarder.",
            'risk_weight' => 10,
            'specialties' => json_encode(['gynécologie', 'oncologie']),
        ]);

        // Nœud de fin : contrôle conseillé
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_end_checkup',
        ], [
            'dynamic_question_template_id' => null,
            'order' => 12,
            'branches' => null,
            'node_type' => 'end',
            'outcome_message' => "Un contrôle médical est recommandé. Consultez dans les prochaines semaines.",
            'risk_weight' => 5,
            'specialties' => json_encode(['gynécologie']),
        ]);
    }
}
