<?php

namespace App\Http\Controllers\Capsule;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Capsule\CapsuleItem;


class CapsuleItemController extends Controller
{
    /**
     * Afficher un élément spécifique de capsule.
     */
    public function show($id)
    {
        return response()->json(
            CapsuleItem::findOrFail($id)
        );
    }

    /**
     * Liste des items d’une capsule donnée.
     */
    public function byCapsule($capsuleId)
    {
        $items = CapsuleItem::where('capsule_id', $capsuleId)
            ->orderBy('position')
            ->get();

        return response()->json($items);
    }
}