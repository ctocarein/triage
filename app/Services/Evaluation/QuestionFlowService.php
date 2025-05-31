<?php

namespace App\Services\Evaluation;

use App\Models\Evaluation\QuestionGroup;
use App\Models\Evaluation\EvaluationSession;
use App\Models\Evaluation\DynamicQuestionTemplate;

class QuestionFlowService
{
    /**
     * Récupère les questions visibles pour un groupe donné, en appliquant les logiques conditionnelles.
     *
     * @param int $groupId ID du groupe de questions.
     * @param array $answers Réponses actuelles pour évaluer la visibilité des questions.
     * @return \Illuminate\Support\Collection Collection de questions visibles.
     */
    public function getVisibleQuestions(int $groupId, array $answers = []): \Illuminate\Support\Collection
    {
         $questions = DynamicQuestionTemplate::with('group')
            ->where('question_group_id', $groupId)
            ->orderBy('order')
            ->get();

        // Appliquer les logiques conditionnelles de visibilité
        return $questions->filter(function ($question) use ($answers) {
            $visibleIf = is_string($question->visible_if)
                ? json_decode($question->visible_if, true)
                : $question->visible_if;

            if (!is_array($visibleIf)) return true;

            foreach ($visibleIf as $condition) {
                $qKey = $condition['question'] ?? null;
                $expected = $condition['value'] ?? null;
                $actual = $answers[$qKey] ?? null;
                $operator = $condition['operator'] ?? '=';

                switch ($operator) {
                    case '=':      if ($actual != $expected) return false; break;
                    case '!=':     if ($actual == $expected) return false; break;
                    case '>':      if (!($actual > $expected)) return false; break;
                    case '<':      if (!($actual < $expected)) return false; break;
                    case 'in':     if (!in_array($actual, (array)$expected)) return false; break;
                    case 'not_in': if (in_array($actual, (array)$expected)) return false; break;
                    default:       return false;
                }
            }
            
            return true;
        })->values();
    }


    public function getEntryPoints(?string $target = null)
    {
        $query = DynamicQuestionTemplate::where('is_entry_point', true)->orderBy('order');

        if ($target) {
            $query->where('answer_target', $target);
        }

        return $query->get();
    }

    /**
     * Trouve le prochain groupe de questions à afficher.
     *
     * @param string|null $currentGroupKey Clé du groupe actuel, ou null pour le premier groupe.
     * @param EvaluationSession $session Session d'évaluation en cours.
     * @return string|null Clé du prochain groupe, ou null si aucun groupe suivant n'existe.
     */
    public function getNextQuestions(EvaluationSession $session): array
    {
        $groupId = optional($session->currentQuestion)->question_group_id;

        if (!$groupId) {
            return [];
        }

        $answers = $session->answers()->get()->keyBy('dynamic_question_template_id');

        $questions = DynamicQuestionTemplate::with('group')
            ->where('question_group_id', $groupId)
            ->orderBy('order')
            ->get();

        return $questions->filter(function ($question) use ($answers) {
            $visibleIf = is_string($question->visible_if)
                ? json_decode($question->visible_if, true)
                : $question->visible_if;

            if (!is_array($visibleIf)) return true;

            foreach ($visibleIf as $condition) {
                $qKey = $condition['question'] ?? null;
                $expected = $condition['value'] ?? null;
                $operator = $condition['operator'] ?? '=';

                $matched = DynamicQuestionTemplate::where('key', $qKey)->first();
                if (!$matched) continue;

                $actual = optional($answers->get($matched->id))->value;

                switch ($operator) {
                    case '=':      if ($actual != $expected) return false; break;
                    case '!=':     if ($actual == $expected) return false; break;
                    case 'in':     if (!in_array($actual, (array) $expected)) return false; break;
                    case 'not_in': if (in_array($actual, (array) $expected)) return false; break;
                    case '>':      if (!($actual > $expected)) return false; break;
                    case '<':      if (!($actual < $expected)) return false; break;
                    default:       return false;
                }
            }

            return true;
        })->values()->all();
    }


    public function nextGroup(?string $currentGroupKey, EvaluationSession $session): ?string
{
    // Tous les groupes actifs, triés par ordre
    $groups = QuestionGroup::where('is_active', true)
        ->orderBy('order')
        ->pluck('key')
        ->toArray();

    // ✅ Si aucun groupe courant n'est fourni, prendre le premier
    if (is_null($currentGroupKey)) {
        $currentGroupKey = $session->currentQuestion?->group?->key ?? null;

        // Si on ne peut pas déterminer le groupe courant, démarrer au début
        if (is_null($currentGroupKey)) {
            return $groups[0] ?? null;
        }
    }

    // Trouver la position du groupe actuel
    $index = array_search($currentGroupKey, $groups);

    if ($index === false || $index + 1 >= count($groups)) {
        return null; // 🚫 Fin du flux ou groupe inconnu
    }

    $nextKey = $groups[$index + 1];

    // 🔍 Logique conditionnelle pour sauter certains groupes
    if ($nextKey === 'symptom_cervix') {
        $age = optional($session->account->personalProfile)->age();
        if ($age && $age < 22) {
            return $this->nextGroup($nextKey, $session); // 🔁 Sauter vers le suivant
        }
    }

    return $nextKey;
}


    
}
