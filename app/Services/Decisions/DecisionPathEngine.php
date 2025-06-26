<?php

namespace App\Services;

use App\Models\DecisionPath;
use App\Models\DecisionNode;
use App\Models\EvaluationSession;

class DecisionPathEngine
{
    protected DecisionPath $decisionPath;

    public function __construct(DecisionPath $decisionPath)
    {
        $this->decisionPath = $decisionPath;
    }

    /**
     * Récupère le premier node du parcours (entrée de l’arbre)
     */
    public function getFirstNode(): ?DecisionNode
    {
        // Suppose que le premier node a order = 1, ou un flag "is_entry"
        return $this->decisionPath->nodes()->orderBy('order')->first();
    }

    /**
     * À partir de la session, retourne le node/question suivant.
     * @param EvaluationSession $session
     * @return DecisionNode|null
     */
    public function getNextNode(EvaluationSession $session): ?DecisionNode
    {
        // 1️⃣ — Récupère les réponses
        $answers = $session->answers()->orderBy('created_at')->get()->keyBy('dynamic_question_template_id');
        $currentNode = null;

        // 2️⃣ — Trouver le node courant
        if ($session->current_question_id) {
            $currentNode = $this->decisionPath->nodes()
                ->where('dynamic_question_template_id', $session->current_question_id)
                ->first();
        }

        // 3️⃣ — Si aucune réponse encore, retourne le premier node
        if (!$currentNode) {
            return $this->getFirstNode();
        }

        // 4️⃣ — Logique de branchement
        $branches = $currentNode->branches ?? [];
        if (!is_array($branches)) {
            $branches = json_decode($branches, true) ?? [];
        }

        $answer = $answers[$currentNode->dynamic_question_template_id]->value ?? null;
        $nextNodeKey = null;

        // Branche sur la valeur de réponse si elle existe
        if ($answer !== null && isset($branches[$answer])) {
            $nextNodeKey = $branches[$answer];
        } elseif (isset($branches['default'])) {
            $nextNodeKey = $branches['default'];
        }

        // Recherche le prochain node selon le key ou la séquence
        if ($nextNodeKey) {
            return $this->decisionPath->nodes()->where('node_key', $nextNodeKey)->first();
        } else {
            // Sinon, retourne le prochain dans l’ordre (ou null si terminé)
            return $this->decisionPath->nodes()
                ->where('order', '>', $currentNode->order)
                ->orderBy('order')->first();
        }
    }
}
