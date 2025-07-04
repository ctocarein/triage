<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Decisions\DecisionPath;
use App\Models\Decisions\DecisionNode;
use App\Models\Evaluation\DynamicQuestionTemplate;

class DecisionPathBreastMassSeeder extends Seeder
{
    public function run()
    {
        $path = DecisionPath::updateOrCreate([
            'key' => 'dp_breast_mass',
        ], [
            'title' => 'Masse mammaire',
            'description' => 'Parcours d’évaluation en cas de masse/boule dans le sein.',
            'category' => 'Sein',
            'source_guideline' => 'OMS, HAS',
            'version' => '1.0',
            'is_active' => true,
        ]);

        // 1️⃣ — Définis ici toutes les clés patterns que tu veux inclure dans ce parcours
        $keyArray = [
            'breast_mass%',
            'breast_nipple_bleeding%',     // Ex : si tu veux tout ce qui commence par ces prefixes
            'breast_skin_change%',     // Ex : si tu veux tout ce qui commence par ces prefixes
            'breast_asymmetry%',     // Ex : si tu veux tout ce qui commence par ces prefixes
            'axillary_ganglion%',     // Ex : si tu veux tout ce qui commence par ces prefixes
            // Ajoute autant que nécessaire selon l’évolution des questions
        ];

        // 2️⃣ — Récupère toutes les questions concernées dynamiquement
        $query = DynamicQuestionTemplate::where('bloc_key', 'symptom_breast');
        $query->where(function ($q) use ($keyArray) {
            foreach ($keyArray as $pattern) {
                $q->orWhere('key', 'like', $pattern);
            }
        });
        $questions = $query->orderBy('order')->get()->keyBy('key');

        // 3️⃣ — Génère dynamiquement les DecisionNodes en fonction des questions trouvées
        $order = 1;
        $prevNodeKey = null;

        foreach ($questions as $qKey => $q) {
            $nodeKey = "node_" . $qKey;
            DecisionNode::updateOrCreate([
                'decision_path_id' => $path->id,
                'node_key' => $nodeKey,
            ], [
                'dynamic_question_template_id' => $q->id,
                'order' => $order++,
                'node_type' => 'question',
                'branches' => json_encode(['default' => null]), // Ajoute ta logique de branchement ici si besoin
            ]);

            // Branche le node précédent vers ce node
            if ($prevNodeKey) {
                $prevNode = DecisionNode::where('decision_path_id', $path->id)->where('node_key', $prevNodeKey)->first();
                if ($prevNode) {
                    $branches = $prevNode->branches ?? [];
                    if (!is_array($branches)) $branches = json_decode($branches, true) ?? [];
                    $branches['default'] = $nodeKey;
                    $prevNode->branches = json_encode($branches);
                    $prevNode->save();
                }
            }
            $prevNodeKey = $nodeKey;
        }

        // 4️⃣ — Ajoute un node de fin pour terminer le flow
        $endNodeKey = 'node_end_breast_mass';
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => $endNodeKey,
        ], [
            'order' => $order,
            'node_type' => 'end',
            'outcome_message' => "Masse mammaire détectée, consultez un spécialiste.",
            'risk_weight' => 20,
            'specialties' => json_encode(['gynécologie', 'oncologie']),
        ]);

        // Branche le dernier node question vers le node de fin
        if ($prevNodeKey) {
            $lastNode = DecisionNode::where('decision_path_id', $path->id)->where('node_key', $prevNodeKey)->first();
            if ($lastNode) {
                $branches = $lastNode->branches ?? [];
                if (!is_array($branches)) $branches = json_decode($branches, true) ?? [];
                $branches['default'] = $endNodeKey;
                $lastNode->branches = json_encode($branches);
                $lastNode->save();
            }
        }
    }
}
