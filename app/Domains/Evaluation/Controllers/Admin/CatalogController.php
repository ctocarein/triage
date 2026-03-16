<?php

namespace App\Domains\Evaluation\Controllers\Admin;

use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\ThematicBloc;
use App\Domains\Evaluation\Services\Catalog\CatalogConsistencyChecker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Vue d'ensemble du catalogue pour le tableau de bord admin.
 */
class CatalogController extends Controller
{
    /**
     * Résumé complet du catalogue.
     * GET /admin/catalog/summary
     *
     * Retourne pour chaque bloc :
     *   - métadonnées du bloc (clé, label, ordre, statut, visible_if)
     *   - nb total de questions
     *   - nb questions actives
     *   - nb questions inactives
     *   - types présents
     *   - answer_targets présents
     */
    public function summary(): JsonResponse
    {
        $blocs = ThematicBloc::orderBy('order')->get();

        $questionsByBloc = DynamicQuestionTemplate::select([
                'bloc_key',
                'is_active',
                'type',
                'answer_target',
            ])
            ->get()
            ->groupBy('bloc_key');

        $summary = $blocs->map(function (ThematicBloc $bloc) use ($questionsByBloc) {
            $questions = $questionsByBloc->get($bloc->key, collect());
            $active    = $questions->where('is_active', true);
            $inactive  = $questions->where('is_active', false);

            return [
                'id'               => $bloc->id,
                'key'              => $bloc->key,
                'label'            => $bloc->label,
                'order'            => $bloc->order,
                'is_active'        => $bloc->is_active,
                'color'            => $bloc->color,
                'icon'             => $bloc->icon,
                'visible_if'       => $bloc->visible_if,
                'questions' => [
                    'total'    => $questions->count(),
                    'active'   => $active->count(),
                    'inactive' => $inactive->count(),
                    'types'    => $active->pluck('type')->unique()->values(),
                    'targets'  => $active->pluck('answer_target')->filter()->unique()->values(),
                ],
            ];
        });

        // Questions orphelines (bloc_key ne correspond à aucun ThematicBloc)
        $knownBlocKeys = $blocs->pluck('key');
        $orphans = DynamicQuestionTemplate::whereNotNull('bloc_key')
            ->whereNotIn('bloc_key', $knownBlocKeys)
            ->where('is_active', true)
            ->select(['id', 'key', 'bloc_key'])
            ->get();

        return response()->json([
            'blocs'            => $summary,
            'orphan_questions' => $orphans,
            'totals'           => [
                'blocs'               => $blocs->count(),
                'blocs_active'        => $blocs->where('is_active', true)->count(),
                'questions_total'     => DynamicQuestionTemplate::count(),
                'questions_active'    => DynamicQuestionTemplate::where('is_active', true)->count(),
                'questions_inactive'  => DynamicQuestionTemplate::where('is_active', false)->count(),
            ],
        ]);
    }

    /**
     * Résultat de la vérification de cohérence du catalogue.
     * GET /admin/catalog/check
     */
    public function check(CatalogConsistencyChecker $checker): JsonResponse
    {
        $errors = $checker->check();

        return response()->json([
            'valid'  => empty($errors),
            'errors' => $errors,
        ]);
    }
}
