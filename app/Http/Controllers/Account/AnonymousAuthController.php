<?php


namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Support\Str;

class AnonymousAuthController extends Controller
{
      public function __invoke(Request $request)
    {
        $request->validate([
            'phone_uuid' => 'required|string|unique:accounts,phone_uuid,NULL,id,is_anonymous,1',
        ]);

        $uuid = Str::uuid()->toString();

        // 🔒 Vérifie qu'aucun compte anonyme n'existe déjà sur ce device
        $existing = Account::where('phone_uuid', $request->phone_uuid)
            ->where('is_anonymous', true)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'phone_uuid' => ['Un compte anonyme est déjà actif sur cet appareil.'],
            ]);
        }

        // 🆕 Création du compte anonyme
        $account = Account::create([
            'phone' => 'anon_' . $uuid,
            'pin_code' => bcrypt(Str::random(6)), // inutilisable
            'is_anonymous' => true,
            'is_anonymous_at' => now(),
            'is_active' => true,
            'phone_uuid' => $request->phone_uuid,
        ]);

        $token = $account->createToken('anon-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'account' => [
                'id' => $account->id,
                'is_anonymous' => true,
                'is_active' => true,
                'phone_uuid' => $account->phone_uuid,
                'created_at' => $account->created_at->toDateTimeString(),
                'is_anonymous_at' => $account->is_anonymous_at?->toDateTimeString(),
            ]
        ], 201);
    }
}
