<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Decision\Models\DecisionPath;
use App\Domains\Decision\Models\DecisionNode;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;

class DecisionPathBreastPainSeeder extends Seeder
{
    public function run()
    {
        // 1️⃣ — Crée/Màj le DecisionPath spécifique “Douleur au sein”
        $path = DecisionPath::updateOrCreate([
            'key' => 'dp_breast_pain',
        ], [
            'title' => 'Douleur au sein',
            'description' => 'Triage rapide et orienté en cas de douleur mammaire',
            'category' => 'Sein',
            'source_guideline' => 'OMS, HAS',
            'version' => '1.0',
            'is_active' => true,
        ]);

        // 2️⃣ — Sélectionne dynamiquement toutes les questions "breast_pain" du bloc sein
        $questions = DynamicQuestionTemplate::where('bloc_key', 'symptom_breast')
            ->where('key', 'like', 'breast_pain%')
            ->orderBy('order') // ou un autre critère si tu veux l’ordre exact
            ->get();

        $order = 1;
        $lastNodeKey = null;
        foreach ($questions as $q) {
            $nodeKey = "node_" . $q->key;

            // Enregistre le node
            DecisionNode::updateOrCreate([
                'decision_path_id' => $path->id,
                'node_key' => $nodeKey,
            ], [
                'dynamic_question_template_id' => $q->id,
                'order' => $order++,
                'node_type' => 'question',
                // Tu peux enrichir ici la logique de branchement si besoin
            ]);

            $lastNodeKey = $nodeKey;
        }

        // 3️⃣ — Node de fin (par exemple : rassurant OU alerte selon ta logique)
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_end_breast_pain',
        ], [
            'order' => 99,
            'node_type' => 'end',
            'outcome_message' => "Évaluation terminée. Si la douleur persiste, consultez un professionnel de santé.",
            'risk_weight' => 0,
            'specialties' => json_encode(['sage-femme', 'gynécologie']),
        ]);

        // 4️⃣ — (Optionnel) Tu peux chaîner la dernière question vers le node de fin
        if ($lastNodeKey) {
            DecisionNode::where([
                'decision_path_id' => $path->id,
                'node_key' => $lastNodeKey,
            ])->update([
                'branches' => json_encode(['default' => 'node_end_breast_pain']),
            ]);
        }
    }
}
