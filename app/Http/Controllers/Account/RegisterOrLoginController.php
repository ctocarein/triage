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
        // dd($request->all());
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
            ]);
        }

        // 🆕 Cas 2 : nouveau compte → création en attente de PIN
        $newAccount = Account::create([
            'phone' => $data['phone'],
            'phone_uuid' => $data['phone_uuid'] ?? null,
            'needs_assistance' => $data['needs_assistance'] ?? false,
            'is_active' => true,
        ]);

        return response()->json([
            'status' => 'set_pin_required',
            'message' => 'Nouveau compte créé. Définissez un code PIN.',
            'account_id' => $newAccount->id
        ], 201);
    }
}
