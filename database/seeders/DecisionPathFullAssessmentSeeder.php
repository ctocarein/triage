<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\Decision\Models\DecisionNode;
use App\Domains\Decision\Models\DecisionPath;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use Illuminate\Support\Facades\DB;

class DecisionPathFullAssessmentSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function (): void {
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

            // Ordre canonique du parcours par défaut (routing_rules peut override à runtime).
            // Les blocs "routing-only" (ex: consent_declined) ne doivent pas être ici.
            $blocKeys = [
                'greeting',
                'profil_identite',
                'profil_socio',
                'medical_profile',
                'symptom_breast',
                'symptom_cervix',
                'lifestyle',
            ];

            // On reconstruit entièrement les nodes pour éviter les résidus d'anciens seeders.
            DecisionNode::where('decision_path_id', $path->id)->delete();

            $order = 1;
            $previousNodeKey = null;

            foreach ($blocKeys as $blocKey) {
                $questions = DynamicQuestionTemplate::query()
                    ->where('bloc_key', $blocKey)
                    ->orderByDesc('is_entry_point')
                    ->orderBy('order')
                    ->orderBy('id')
                    ->get();

                foreach ($questions as $q) {
                    $nodeKey = 'node_' . $q->key;

                    DecisionNode::create([
                        'decision_path_id' => $path->id,
                        'node_key' => $nodeKey,
                        'dynamic_question_template_id' => $q->id,
                        'order' => $order++,
                        'branches' => json_encode(['default' => null]),
                        'node_type' => 'question',
                    ]);

                    if ($previousNodeKey) {
                        DecisionNode::query()
                            ->where('decision_path_id', $path->id)
                            ->where('node_key', $previousNodeKey)
                            ->update(['branches' => json_encode(['default' => $nodeKey])]);
                    }

                    $previousNodeKey = $nodeKey;
                }
            }

            $endNodeKey = 'node_end_normal';

            DecisionNode::create([
                'decision_path_id' => $path->id,
                'node_key' => $endNodeKey,
                'dynamic_question_template_id' => null,
                'order' => $order++,
                'branches' => null,
                'node_type' => 'end',
                'outcome_message' => "Aucun risque significatif détecté. Continuez la surveillance régulière.",
                'risk_weight' => 0,
                'specialties' => null,
            ]);

            if ($previousNodeKey) {
                DecisionNode::query()
                    ->where('decision_path_id', $path->id)
                    ->where('node_key', $previousNodeKey)
                    ->update(['branches' => json_encode(['default' => $endNodeKey])]);
            }

            $count = DecisionNode::query()->where('decision_path_id', $path->id)->count();
            $this->command && $this->command->info("Chemin complet seedé ($count nodes) pour '{$path->title}'");
        });
    }
}
