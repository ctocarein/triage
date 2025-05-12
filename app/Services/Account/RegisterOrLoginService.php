<?php

namespace App\Services\Account;

use App\Models\Account;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RegisterOrLoginService
{
    public function handle(array $data): array
    {
        $key = 'login:' . request()->ip() . ':' . ($data['phone'] ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'phone' => ['Trop de tentatives. Veuillez patienter.'],
            ]);
        }

        RateLimiter::hit($key, 60); // bloqué 1 min

        $account = Account::where('phone', $data['phone'])->first();

        // Cas : compte déjà existant
        if ($account) {
            // Vérifier code PIN
            if (!Hash::check($data['pin_code'], $account->pin_code)) {
                throw ValidationException::withMessages([
                    'pin_code' => ['Code PIN invalide.'],
                ]);
            }

            // Cas : compte anonyme depuis plus de 60 jours
            if ($account->is_anonymous && $account->is_anonymous_at && Carbon::parse($account->is_anonymous_at)->diffInDays(now()) >= 60) {
                throw ValidationException::withMessages([
                    'account' => ['Votre session anonyme a expiré. Veuillez créer un compte pour continuer.'],
                ]);
            }

            // Mise à jour éventuelle
            if (!empty($data['phone_uuid']) && $account->phone_uuid !== $data['phone_uuid']) {
                $account->phone_uuid = $data['phone_uuid'];
            }

            $account->last_login_at = now();
            $account->save();
        }

        // Cas : compte inexistant → création
        if (!$account) {
            $account = Account::create([
                'phone' => $data['phone'],
                'pin_code' => bcrypt($data['pin_code'] ?? Str::random(6)),
                'phone_uuid' => $data['phone_uuid'] ?? null,
                'needs_assistance' => $data['needs_assistance'] ?? false,
                'is_anonymous' => false,
                'is_anonymous_at' => null,
                'is_active' => true,
                'last_login_at' => now(),
            ]);
        }

        $token = $account->createToken('account-token')->plainTextToken;

        return [
            'token' => $token,
            'account' => $account,
        ];
    }
}
