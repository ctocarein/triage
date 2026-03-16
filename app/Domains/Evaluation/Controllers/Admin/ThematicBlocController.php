<?php

namespace App\Domains\Evaluation\Controllers\Admin;

use App\Domains\Evaluation\DTOs\Admin\ThematicBlocRequest;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use App\Domains\Evaluation\Models\ThematicBloc;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Annotations as OA;

class ThematicBlocController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ThematicBloc::query()
            ->with('questionGroup:id,key,label')
            ->withCount([
                'questions as total_questions',
                'questions as active_questions' => fn ($q) => $q->where('is_active', true),
            ]);

        if ($request->filled('question_group_id')) {
            $query->where('question_group_id', $request->input('question_group_id'));
        }

        if ($request->boolean('inactive_only')) {
            $query->where('is_active', false);
        }

        if ($request->filled('q')) {
            $search = $request->input('q');
            $query->where(fn ($q) =>
                $q->where('key', 'like', "%{$search}%")
                  ->orWhere('label', 'like', "%{$search}%")
            );
        }

        return response()->json($query->orderBy('order')->get());
    }

    public function show(ThematicBloc $bloc): JsonResponse
    {
        $bloc->load('questionGroup:id,key,label');
        $bloc->loadCount([
            'questions as total_questions',
            'questions as active_questions' => fn ($q) => $q->where('is_active', true),
        ]);

        return response()->json($bloc);
    }

    /**
     * Questions appartenant à ce bloc, ordonnées.
     * GET /admin/thematic-blocs/{bloc}/questions
     */
    public function questions(ThematicBloc $bloc, Request $request): JsonResponse
    {
        $query = $bloc->questions();

        if ($request->boolean('inactive_only')) {
            $query->where('is_active', false);
        } elseif (! $request->boolean('include_inactive')) {
            $query->where('is_active', true);
        }

        if ($request->filled('q')) {
            $s = $request->input('q');
            $query->where(fn ($q) =>
                $q->where('key', 'like', "%{$s}%")->orWhere('text', 'like', "%{$s}%")
            );
        }

        return response()->json(
            $query->orderBy('order')->get()
        );
    }

    public function store(ThematicBlocRequest $request): JsonResponse
    {
        $bloc = ThematicBloc::create($request->validated());

        return response()->json($bloc->load('questionGroup:id,key,label'), 201);
    }

    public function update(ThematicBlocRequest $request, ThematicBloc $bloc): JsonResponse
    {
        $bloc->update($request->validated());

        return response()->json($bloc->fresh('questionGroup'));
    }

    /**
     * Suppression protégée : bloquée si des questions actives utilisent ce bloc.
     * DELETE /admin/thematic-blocs/{bloc}
     */
    public function destroy(ThematicBloc $bloc): JsonResponse
    {
        $activeCount = $bloc->questions()->where('is_active', true)->count();

        if ($activeCount > 0) {
            return response()->json([
                'error'   => "Ce bloc contient {$activeCount} question(s) active(s). Désactivez-les ou réaffectez-les avant de supprimer le bloc.",
                'count'   => $activeCount,
            ], 409);
        }

        // Questions inactives : on les détache du bloc (bloc_key → null)
        $bloc->questions()->update(['bloc_key' => null]);
        $bloc->delete();

        return response()->json(['message' => 'Bloc supprimé.']);
    }

    public function toggle(ThematicBloc $bloc): JsonResponse
    {
        $bloc->update(['is_active' => ! $bloc->is_active]);

        return response()->json([
            'id'        => $bloc->id,
            'key'       => $bloc->key,
            'is_active' => $bloc->is_active,
        ]);
    }

    /**
     * Réordonner les blocs (drag & drop).
     * PATCH /admin/thematic-blocs/reorder
     * Body: { "order": [{"id": 3, "order": 1}, {"id": 1, "order": 2}, ...] }
     */
    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'order'         => ['required', 'array', 'min:1'],
            'order.*.id'    => ['required', 'integer', 'exists:thematic_blocs,id'],
            'order.*.order' => ['required', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->input('order') as $item) {
                ThematicBloc::where('id', $item['id'])->update(['order' => $item['order']]);
            }
        });

        return response()->json(
            ThematicBloc::with('questionGroup:id,key,label')
                ->withCount('questions')
                ->orderBy('order')
                ->get()
        );
    }
}
