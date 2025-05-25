<?php

namespace App\Http\Controllers\Account;

use App\Models\Account;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SetPinCodeController extends Controller
{
    /**
     * Set the PIN code for an account.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'pin_code' => 'required|string|min:4|max:6',
        ]);

        $account = Account::find($request->account_id);

        if ($account->pin_code) {
            return response()->json(['message' => 'PIN déjà défini'], 400);
        }

        $account->pin_code = bcrypt($request->pin_code);
        $account->save();

        $token = $account->createToken('account-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'account' => [
                'id' => $account->id,
                'phone' => $account->phone,
                'type' => $account->type,
                'is_active' => $account->is_active,
                'is_anonymous' => $account->is_anonymous,
            ]
        ]);
    }


    /**
     * Request a PIN reset for an account.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function requestReset(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $account = Account::where('phone', $request->phone)->first();

        if (!$account) {
            return response()->json(['message' => 'Numéro inconnu.'], 404);
        }

        if ($account->is_anonymous) {
            return response()->json(['message' => 'Ce compte est anonyme.'], 403);
        }

        $resetToken = bin2hex(random_bytes(16));
        cache()->put("reset_pin_{$account->id}", $resetToken, now()->addMinutes(15));

        return response()->json([
            'reset_token' => $resetToken,
            'message' => 'Veuillez confirmer votre identité pour réinitialiser votre code PIN.'
        ]);
    }

    /**
     * Reset the PIN code for an account.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function confirmReset(Request $request)
    {
        $request->validate([
            'reset_token' => 'required|string',
            'new_pin' => 'required|string|min:4|max:6',
        ]);

        $accountId = collect(cache()->getKeys())
            ->first(fn ($key) => str_starts_with($key, 'reset_pin_') && cache()->get($key) === $request->reset_token);

        if (!$accountId) {
            return response()->json(['message' => 'Token invalide ou expiré.'], 403);
        }

        $accountId = str_replace('reset_pin_', '', $accountId);
        $account = Account::find($accountId);

        $account->pin_code = bcrypt($request->new_pin);
        $account->save();

        cache()->forget("reset_pin_{$account->id}");

        return response()->json(['message' => 'Code PIN mis à jour avec succès.']);
    }


}
