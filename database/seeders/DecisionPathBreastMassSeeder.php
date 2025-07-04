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
            'category' => 'breast',
            'source_guideline' => 'OMS, HAS',
            'version' => '1.0',
            'is_active' => true,
        ]);

        $questions = DynamicQuestionTemplate::where('bloc_key', 'symptom_breast')
            ->where(function($query) {
                $query->where('key', 'like', 'breast_mass%');
            })
            ->orderBy('order')
            ->get()
            ->keyBy('key');

        $order = 1;

        // 1️⃣ — Node: "Avez-vous remarqué une boule ou une masse dans le sein ?"
        $first = $questions['breast_mass'] ?? null;
        if ($first) {
            DecisionNode::updateOrCreate([
                'decision_path_id' => $path->id,
                'node_key' => 'node_breast_mass',
            ], [
                'dynamic_question_template_id' => $first->id,
                'order' => $order++,
                'node_type' => 'question',
                'branches' => json_encode(['default' => 'node_breast_mass_duration']),
            ]);
        }

        // 2️⃣ — Node: "Depuis combien de temps avez-vous remarqué cette masse ?"
        $duration = $questions['breast_mass_duration'] ?? null;
        if ($duration) {
            // On branche selon la valeur choisie
            DecisionNode::updateOrCreate([
                'decision_path_id' => $path->id,
                'node_key' => 'node_breast_mass_duration',
            ], [
                'dynamic_question_template_id' => $duration->id,
                'order' => $order++,
                'node_type' => 'question',
                // Ici, la clé 'more_3m' de la réponse pointe vers alerte
                'branches' => json_encode([
                    'more_3m' => 'node_end_breast_mass_urgent',
                    'default' => 'node_end_breast_mass',
                ]),
            ]);
        }

        // 3️⃣ — Node de fin “suivi standard”
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_end_breast_mass',
        ], [
            'order' => 99,
            'node_type' => 'end',
            'outcome_message' => "Masse mammaire récente. Consultation spécialisée conseillée.",
            'risk_weight' => 15,
            'specialties' => json_encode(['gynécologie']),
        ]);

        // 4️⃣ — Node de fin “urgence”
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => 'node_end_breast_mass_urgent',
        ], [
            'order' => 100,
            'node_type' => 'end',
            'outcome_message' => "Masse présente depuis plus de 3 mois. Urgence : consultation rapide en oncologie.",
            'risk_weight' => 25,
            'specialties' => json_encode(['oncologie']),
        ]);
    }
}
