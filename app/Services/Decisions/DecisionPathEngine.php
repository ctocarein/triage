<?php

namespace App\Services\Decisions;

use App\Domains\Decision\Models\DecisionNode;
use App\Domains\Decision\Models\DecisionPath;
use App\Domains\Evaluation\Models\EvaluationSession;

/**
 * DecisionPathEngine gere la logique de parcours d'un DecisionPath.
 * Il permet de naviguer dans les nodes en fonction des reponses fournies.
 */
class DecisionPathEngine
{
    protected DecisionPath $decisionPath;

    public function __construct(DecisionPath $decisionPath)
    {
        $this->decisionPath = $decisionPath;
    }

    /**
     * Recupere le premier node du parcours (entree de l'arbre).
     */
    public function getFirstNode(): ?DecisionNode
    {
        // Suppose que le premier node a order = 1, ou un flag "is_entry".
        return $this->decisionPath->nodes()->orderBy('order')->first();
    }

    /**
     * A partir de la session, retourne le node/question suivant.
     */
    public function getNextNode(EvaluationSession $session): ?DecisionNode
    {
        $answers = $session->answers()
            ->orderBy('created_at')
            ->get();
        $answersByQuestionId = $answers->keyBy('question_id');
        $answersByLegacyId = $answers->keyBy('dynamic_question_template_id');

        $currentNode = null;

        if ($session->current_question_id) {
            $currentNode = $this->decisionPath->nodes()
                ->where('question_id', $session->current_question_id)
                ->first();

            if (! $currentNode) {
                $currentNode = $this->decisionPath->nodes()
                    ->where('dynamic_question_template_id', $session->current_question_id)
                    ->first();
            }
        }

        if (!$currentNode) {
            return $this->getFirstNode();
        }

        $branches = $currentNode->branches ?? [];
        if (!is_array($branches)) {
            $branches = json_decode($branches, true) ?? [];
        }

        $nodeQuestionId = $currentNode->question_id ?? $currentNode->dynamic_question_template_id ?? null;
        $answer = null;
        if ($nodeQuestionId !== null) {
            $answer = $answersByQuestionId[$nodeQuestionId]->value
                ?? $answersByLegacyId[$nodeQuestionId]->value
                ?? null;
        }
        $nextNodeKey = null;

        $nextNodeKey = $this->resolveNextNodeKey($branches, $answer);
        if ($nextNodeKey === null && isset($branches['default'])) {
            $nextNodeKey = $branches['default'];
        }

        if ($nextNodeKey) {
            return $this->decisionPath->nodes()->where('node_key', $nextNodeKey)->first();
        }

        return $this->decisionPath->nodes()
            ->where('order', '>', $currentNode->order)
            ->orderBy('order')
            ->first();
    }

    protected function resolveNextNodeKey(array $branches, mixed $answer): ?string
    {
        if ($answer === null) {
            return null;
        }

        foreach ($this->normalizeBranchCandidates($answer) as $candidate) {
            if (array_key_exists($candidate, $branches)) {
                return $branches[$candidate];
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeBranchCandidates(mixed $answer): array
    {
        if (is_array($answer)) {
            $candidates = [];
            foreach ($answer as $item) {
                $candidates = array_merge($candidates, $this->normalizeBranchCandidates($item));
            }
            return array_values(array_unique($candidates));
        }

        if (is_bool($answer)) {
            return [
                $answer ? 'true' : 'false',
                $answer ? '1' : '0',
                $answer ? 'yes' : 'no',
                $answer ? 'oui' : 'non',
            ];
        }

        if (is_numeric($answer)) {
            $string = (string) $answer;
            $int = (string) ((int) $answer);
            return array_values(array_unique([$string, $int]));
        }

        if (is_string($answer)) {
            $trim = trim($answer);
            $lower = strtolower($trim);
            return array_values(array_unique([$answer, $trim, $lower]));
        }

        return [(string) $answer];
    }
}
