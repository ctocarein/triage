<?php

namespace App\Services\Evaluation;

use App\Models\Evaluation\QuestionGroup;
use App\Models\Evaluation\EvaluationSession;
use App\Models\Evaluation\DynamicQuestionTemplate;

class QuestionFlowService

{



    /**
     * Vérifie si une question est visible en fonction des réponses et du profil.
     *
     * @param array $visibleIf Conditions de visibilité.
     * @param array $answers Réponses actuelles.
     * @param array $profile Profil de l'utilisateur.
     * @return bool True si la question est visible, false sinon.
     */

    private function hasValueInProfile(DynamicQuestionTemplate $question, array $profile): bool
    {
        $target = $question->answer_target;
        $key = $question->key;

        // Vérification dans medical ou personal profile
        if (in_array($target, ['medical_profile', 'personal_profile'])) {
            return !empty($profile[$target][$key]);
        }

        return false;
    }


    /**
     * Vérifie si une question est visible en fonction des réponses et du profil.
     *
     * @param array $visibleIf Conditions de visibilité.
     * @param array $answers Réponses actuelles.
     * @param array $profile Profil de l'utilisateur.
     * @return bool True si la question est visible, false sinon.
     */

   private function isVisible(array $visibleIf = null, array $answers = [], array $profile = []): bool
    {
        if (!$visibleIf) return true;

        foreach ($visibleIf as $condition) {
            $source = $condition['source'] ?? 'question';
            $key    = $condition['key'] ?? $condition['question'] ?? null;
            $value  = $condition['value'] ?? null;
            $op     = $condition['operator'] ?? '=';

            $actual = match ($source) {
                'question'         => $answers[$key] ?? null,
                'profile.medical'  => $profile['medical'][$key] ?? null,
                'profile.personal' => $profile['personal'][$key] ?? null,
                default            => null,
            };

            if (!$this->compare($actual, $value, $op)) return false;
        }

        return true;
    }


    /**
     * Récupère les questions visibles pour un groupe donné, en appliquant les logiques conditionnelles.
     *
     * @param int $groupId ID du groupe de questions.
     * @param array $answers Réponses actuelles pour évaluer la visibilité des questions.
     * @return \Illuminate\Support\Collection Collection de questions visibles.
     */
    public function getVisibleQuestions(int $groupId, array $answers = []): \Illuminate\Support\Collection
    {
        $profile = [ // On suppose que le service reçoit une session ailleurs
            'medical' => [], 'personal' => []
        ];

        return DynamicQuestionTemplate::where('question_group_id', $groupId)
            ->orderBy('order')
            ->get()
            ->filter(fn($q) => $this->shouldBeVisible($q, $answers, $profile))
            ->values();
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
     * Récupère le groupe de questions actif pour une session donnée.
     *
     * @param EvaluationSession $session Session d'évaluation en cours.
     * @return QuestionGroup|null Le groupe de questions actif, ou null si aucun groupe n'est trouvé.
     */

    public function goToNextQuestion(EvaluationSession $session): ?DynamicQuestionTemplate
    {
        $answers = $session->answers->keyBy('dynamic_question_template_id');
        $answeredIds = $answers->keys()->all();

        $profile = [
            'medical' => optional($session->account->medicalProfile)->toArray() ?? [],
            'personal' => optional($session->account->personalProfile)->toArray() ?? [],
        ];

        foreach (DynamicQuestionTemplate::orderBy('order')->get() as $question) {
            if (in_array($question->id, $answeredIds)) continue;

            if ($this->shouldBeVisible($question, $answers->map->value()->toArray(), $profile)) {
                return $question;
            }
        }

        return null;
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
            if (!$groupId) return [];

            $answers = $session->answers->keyBy('dynamic_question_template_id')->map->value()->toArray();
            $profile = [
                'medical' => optional($session->account->medicalProfile)->toArray() ?? [],
                'personal' => optional($session->account->personalProfile)->toArray() ?? [],
            ];

            return DynamicQuestionTemplate::where('question_group_id', $groupId)
                ->orderBy('order')
                ->get()
                ->filter(fn($q) => $this->shouldBeVisible($q, $answers, $profile))
                ->values()
                ->all();
        }

        private function shouldBeVisible(DynamicQuestionTemplate $question, array $answers, array $profile): bool
        {
            if (
                in_array($question->answer_target, ['medical_profile', 'personal_profile']) &&
                $this->hasValueInProfile($question, $profile)
            ) return false;

            $visibleIf = is_string($question->visible_if)
                ? json_decode($question->visible_if, true)
                : $question->visible_if;

            return $this->isVisible($visibleIf ?? [], $answers, $profile);
        }

    /** 
     * Trouve le prochain groupe de questions à afficher.
     *
     * @param string|null $currentGroupKey Clé du groupe actuel, ou null pour le premier groupe.
     * @param EvaluationSession $session Session d'évaluation en cours.
     * @return string|null Clé du prochain groupe, ou null si aucun groupe suivant n'existe.
     */ 
     
    public function nextGroup(?string $currentGroupKey, EvaluationSession $session): ?string
    {
        // 🔄 Récupérer tous les groupes actifs et visibles pour ce profil
        $groups = $this->getActiveGroups($session)->pluck('key')->toArray();

        if (empty($groups)) return null;

        // ✅ Premier groupe par défaut
        if (is_null($currentGroupKey)) {
            $currentGroupKey = $session->currentQuestion?->group?->key ?? null;
            if (is_null($currentGroupKey)) {
                return $groups[0];
            }
        }

        // 🔍 Trouver l’index du groupe courant
        $index = array_search($currentGroupKey, $groups);
        if ($index === false || $index + 1 >= count($groups)) {
            return null; // Fin du flux
        }

        $nextKey = $groups[$index + 1];

        // 🧠 Conditions personnalisées de saut (profil ou score)
        switch ($nextKey) {
            case 'symptom_cervix':
                $age = optional($session->account->personalProfile)->age();
                if ($age && $age < 22) {
                    return $this->nextGroup($nextKey, $session); // ⏩ Skip
                }
                break;

            case 'symptom_breast':
                $age = optional($session->account->personalProfile)->age();
                if ($age && $age < 25) {
                    return $this->nextGroup($nextKey, $session); // ⛔ Enfant, pas concernée
                }
                break;

            // ➕ Tu peux ajouter d'autres cas ici
        }

        return $nextKey;
    }


    /**
     * Compare deux valeurs selon un opérateur donné.
     *
     * @param mixed $actual Valeur actuelle.
     * @param mixed $expected Valeur attendue.
     * @param string $operator Opérateur de comparaison.
     * @return bool Résultat de la comparaison.
     */
    public function getGroupScore(int $groupId, EvaluationSession $session): float
    {
        // Récupère les questions du groupe
        $questions = DynamicQuestionTemplate::where('question_group_id', $groupId)->get();

        // Réponses existantes de la session
        $answers = $session->answers->keyBy('dynamic_question_template_id');

        $total = 0;

        foreach ($questions as $question) {
            $answer = $answers->get($question->id);
            $value = optional($answer)->value;

            // Si réponse donnée et pondération > 0
            if ($value !== null && $question->risk_weight > 0) {
                $total += $question->risk_weight;
            }
        }

        return $total;
    }


    public function getActiveGroups(EvaluationSession $session): \Illuminate\Support\Collection
    {
        $groups = QuestionGroup::where('is_active', true)->orderBy('order')->get();

        // Charger les données de profil
        $profile = [
            'medical' => optional($session->account->medicalProfile)->toArray() ?? [],
            'personal' => optional($session->account->personalProfile)->toArray() ?? [],
        ];

        // Pas de réponses au début
        $answers = [];

        return $groups->filter(function ($group) use ($profile, $answers) {
            $conditions = is_string($group->conditions)
                ? json_decode($group->conditions, true)
                : $group->conditions;

            // Pas de conditions => groupe toujours visible
            if (!is_array($conditions) || empty($conditions)) {
                return true;
            }

            // Utilise isVisible() pour vérifier la logique conditionnelle
            return $this->isVisible($conditions, $answers, $profile);
        })->values();
    }


    public function evaluateGlobalRisk(EvaluationSession $session): array
    {
        $blocs = $this->calculateScore($session);

        $totalScore = collect($blocs)->sum('score');

        $level = 'non déterminé';
        foreach (config('chereh_risk.levels') as $zone) {
            if ($totalScore >= $zone['min'] && $totalScore <= $zone['max']) {
                $level = $zone['name'];
                break;
            }
        }

        return [
            'total_score' => $totalScore,
            'level' => $level,
            'blocs' => $blocs
        ];
    }

    
    
    /**
     * Calcule le score des réponses d'une session d'évaluation.
     *
     * @param EvaluationSession $session
     * @return array
     */
    public function calculateScore(EvaluationSession $session): array
    {
        $answers = $session->answers->keyBy('dynamic_question_template_id');
        $questions = DynamicQuestionTemplate::all();

        // Grouper les réponses par bloc_key
        $blocs = [];

        foreach ($questions as $question) {
            $bloc = $question->bloc_key ?? 'undefined';

            $answer = $answers->get($question->id);
            $value = optional($answer)->value;

            if ($value !== null && $question->risk_weight > 0) {
                if (!isset($blocs[$bloc])) {
                    $blocs[$bloc] = [
                        'score' => 0,
                        'questions' => [],
                        'level' => null,
                        'meta' => $question->meta ?? []
                    ];
                }

                $blocs[$bloc]['score'] += $question->risk_weight;
                $blocs[$bloc]['questions'][] = $question->key;
            }
        }

    // Déterminer le niveau de risque (léger/modéré/sévère)
    foreach ($blocs as $bloc => &$data) {
        $zones = $data['meta']['score_zone'] ?? null;
        if (is_array($zones)) {
            foreach ($zones as $z) {
                if ($data['score'] >= $z['min'] && $data['score'] <= $z['max']) {
                    $data['level'] = $z['level'];
                    break;
                }
            }
        }
    }

    return $blocs;
}
    

private function compare($actual, $expected, string $operator): bool
{
    return match ($operator) {
        '='       => $actual == $expected,
        '!='      => $actual != $expected,
        '>'       => $actual > $expected,
        '<'       => $actual < $expected,
        'in'      => in_array($actual, (array) $expected),
        'not_in'  => !in_array($actual, (array) $expected),
        default   => false,
    };
}

    
}
