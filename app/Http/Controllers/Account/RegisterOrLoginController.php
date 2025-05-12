<?php

namespace App\Http\Controllers\Account;

use App\Models\Account;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Account\RegisterOrLoginService;

class RegisterOrLoginController extends Controller
{
    public function __invoke(Request $request, RegisterOrLoginService $service)
    {
        $request->validate([
            'phone' => 'required|string|min:8|max:20',
            'pin_code' => 'nullable|string|min:4|max:6',
            'phone_uuid' => 'nullable|string',
            'needs_assistance' => 'nullable|boolean',
        ]);

        $data = $request->only(['phone', 'pin_code', 'phone_uuid', 'needs_assistance']);
        $account = Account::where('phone', $data['phone'])->first();

        // Cas : un compte anonyme existe déjà avec ce numéro
        if ($account && $account->is_anonymous) {
            return response()->json([
                'message' => 'Ce numéro est déjà associé à un compte anonyme. Veuillez d’abord le convertir.'
            ], 403);
        }

        // Cas : le compte existe mais aucun PIN fourni
        if ($account && empty($data['pin_code'])) {
            return response()->json([
                'message' => 'Veuillez saisir votre code PIN pour vous connecter.'
            ], 422);
        }

        $response = $service->handle($data);

        return response()->json([
            'token' => $response['token'],
            'account' => [
                'id' => $response['account']->id,
                'phone' => $response['account']->phone,
                'type' => $response['account']->type,
                'marraine_status' => $response['account']->marraine_status,
                'is_active' => $response['account']->is_active,
                'needs_assistance' => $response['account']->needs_assistance,
                'phone_uuid' => $response['account']->phone_uuid,
                'is_anonymous' => $response['account']->is_anonymous,
                'last_login_at' => optional($response['account']->last_login_at)->toDateTimeString(),
            ],
        ]);
    }
}
