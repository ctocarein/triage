<?php


namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Support\Str;

class AnonymousAuthController extends Controller
{
    public function __invoke()
    {
        $uuid = Str::uuid()->toString();

        $account = Account::create([
            'phone' => 'anon_' . $uuid,
            'pin_code' => bcrypt(Str::random(6)), // inutilisable
            'is_anonymous' => true,
            'is_anonymous_at' => now(),
            'is_active' => true,
        ]);

        $token = $account->createToken('anon-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'account' => [
                'id' => $account->id,
                'is_anonymous' => true,
                'is_active' => true,
                'created_at' => $account->created_at->toDateTimeString(),
                'is_anonymous_at' => $account->is_anonymous_at?->toDateTimeString(),
            ]
        ], 201);
    }
}
