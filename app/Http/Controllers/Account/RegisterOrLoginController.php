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
            'pin_code' => 'nullable|string|min:4|max:6', // nullable accepté à la création seulement
            'phone_uuid' => 'nullable|string',
            'needs_assistance' => 'nullable|boolean',
        ]);

        $data = $request->only(['phone', 'pin_code', 'phone_uuid','needs_assistance']);

        $account = Account::where('phone', $data['phone'])->first();

        if ($account && empty($data['pin_code'])) {
            return response()->json([
                'message' => 'PIN code is required for existing accounts.'
            ], 422); // Unprocessable Entity
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
                'last_login_at' => optional($response['account']->last_login_at)->toDateTimeString(),
            ],
        ]);
    }

}
