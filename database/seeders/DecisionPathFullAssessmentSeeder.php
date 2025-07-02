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
        // 1️⃣ Crée ou met à jour le parcours complet
        $path = DecisionPath::updateOrCreate([
            'key' => 'dp_full_assessment',
        ], [
            'title' => 'Évaluation complète CHEREH',
            'description' => 'Parcours global intégrant identité, antécédents, symptômes mammaires et gynécologiques…',
            'category' => 'complete',
            'source_guideline' => 'OMS, HAS, INCa 2024',
            'version' => '1.0',
            'is_active' => true,
        ]);

        // 2️⃣ Liste ordonnée des blocs à inclure (à adapter selon ton projet !)
        $blocKeys = [
            'greeting',           // Bloc accueil (optionnel)
            'personal_profile',   // Identité
            'medical_profile',    // Antécédents
            'symptom_breast',     // Symptômes mammaires
            'symptom_cervical',   // Symptômes gynécologiques
            // 'lifestyle',        // Ajoute ici d’autres blocs si besoin
        ];

        $order = 1;
        $previousNodeKey = null;
        $firstNodeKey = null;

        foreach ($blocKeys as $blocKey) {
            // 3️⃣ Récupère toutes les questions de ce bloc, ordonnées par 'order'
            $questions = DynamicQuestionTemplate::where('bloc_key', $blocKey)
                ->orderBy('order')
                ->get();

            foreach ($questions as $q) {
                $nodeKey = 'node_' . $q->key;

                // 4️⃣ Crée chaque node question
                DecisionNode::updateOrCreate([
                    'decision_path_id' => $path->id,
                    'node_key' => $nodeKey,
                ], [
                    'dynamic_question_template_id' => $q->id,
                    'order' => $order++,
                    'branches' => json_encode(['default' => null]), // Rempli ensuite !
                    'node_type' => 'question',
                ]);

                // 5️⃣ Relie le node précédent au node courant
                if (!$firstNodeKey) $firstNodeKey = $nodeKey;
                if ($previousNodeKey) {
                    DecisionNode::where('decision_path_id', $path->id)
                        ->where('node_key', $previousNodeKey)
                        ->update(['branches' => json_encode(['default' => $nodeKey])]);
                }
                $previousNodeKey = $nodeKey;
            }
        }

        // 6️⃣ Crée le node de fin
        $endNodeKey = 'node_end_normal';
        DecisionNode::updateOrCreate([
            'decision_path_id' => $path->id,
            'node_key' => $endNodeKey,
        ], [
            'dynamic_question_template_id' => null,
            'order' => $order++,
            'branches' => null,
            'node_type' => 'end',
            'outcome_message' => "Aucun risque significatif détecté. Continuez la surveillance régulière.",
            'risk_weight' => 0,
            'specialties' => null,
        ]);

        // 7️⃣ Pointe le dernier node de question vers la fin
        if ($previousNodeKey) {
            DecisionNode::where('decision_path_id', $path->id)
                ->where('node_key', $previousNodeKey)
                ->update(['branches' => json_encode(['default' => $endNodeKey])]);
        }

        // 8️⃣ Personnalisation possible (ex : branchement conditionnel spécifique)
        // Exemple :
        // DecisionNode::where('decision_path_id', $path->id)
        //     ->where('node_key', 'node_breast_pain')
        //     ->update(['branches' => json_encode([
        //         'oui' => 'node_end_risk',
        //         'non' => 'node_next_question_key',
        //         'default' => 'node_next_question_key'
        //     ])]);

        // 9️⃣ (Debug) Affiche le résultat si tu seedes en CLI
        $count = DecisionNode::where('decision_path_id', $path->id)->count();
        $this->command && $this->command->info("Chemin complet seedé ($count nodes) pour '{$path->title}'");
    }
}
