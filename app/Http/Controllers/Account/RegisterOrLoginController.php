<?php

namespace App\Http\Controllers\Account;

use App\Models\Account;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Account\RegisterOrLoginService;

class RegisterOrLoginController extends Controller
{
public function __invoke(Request $request)
{
    $request->validate([
        'phone' => 'required|string|min:8|max:20',
        'phone_uuid' => 'nullable|string',
        'needs_assistance' => 'nullable|boolean'
    ]);

    $data = $request->only(['phone', 'phone_uuid', 'needs_assistance']);

    $account = Account::where('phone', $data['phone'])->first();

    // 🔐 Cas 1 : un compte existe déjà
    if ($account) {
        if ($account->is_anonymous) {
            return response()->json([
                'status' => 'anonymous_conflict',
                'message' => 'Ce numéro est déjà associé à un compte anonyme. Veuillez le convertir.',
            ], 403);
        }

        return response()->json([
            'status' => 'login_required',
            'message' => 'PIN requis pour se connecter',
            'account_id' => $account->id,
            'type' => $account->type,
            'needs_assistance' => $account->needs_assistance,
        ]);
    }

    // 🔐 Cas 2 : empêcher plusieurs comptes anonymes sur un même device
    if (!empty($data['phone_uuid'])) {
        $existingAnonymous = Account::where('phone_uuid', $data['phone_uuid'])
            ->where('is_anonymous', true)
            ->first();

        if ($existingAnonymous) {
            return response()->json([
                'status' => 'device_conflict',
                'message' => 'Un compte anonyme existe déjà sur cet appareil. Veuillez l’utiliser ou le convertir.',
            ], 403);
        }
    }

    // 🆕 Cas 3 : nouveau compte → création en attente de PIN
    $newAccount = Account::create([
        'phone' => $data['phone'],
        'phone_uuid' => $data['phone_uuid'] ?? null,
        'needs_assistance' => $data['needs_assistance'] ?? false,
        'is_active' => true,
        'status' => 'active', // à ne pas oublier si utilisé
    
    ]);

    return response()->json([
        'status' => 'set_pin_required',
        'message' => 'Nouveau compte créé. Définissez un code PIN.',
        'account_id' => $newAccount->id,
        'type' => $newAccount->type,
        'needs_assistance' => $newAccount->needs_assistance,

    ], 201);
}

}
