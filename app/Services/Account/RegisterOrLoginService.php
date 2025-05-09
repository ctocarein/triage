<?php

namespace App\Services\Account;

use App\Models\Account;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class RegisterOrLoginService
{
    public function handle(array $data)
    {
        $key = 'login:' . request()->ip() . ':' . ($data['phone'] ?? 'unknown');

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'phone' => ['Too many attempts. Please wait before retrying.'],
            ]);
        }

        RateLimiter::hit($key, 60); // lock for 1 minute

        if (empty($data['phone'])) {
            throw ValidationException::withMessages([
                'phone' => ['Phone number is required.'],
            ]);
        }

        // Chercher si le compte existe déjà
        $account = Account::where('phone', $data['phone'])->first();

        // Cas 1 : Connexion existante ➔ PIN obligatoire
        if ($account) {
            if (empty($data['pin_code']) || !Hash::check($data['pin_code'], $account->pin_code)) {
                throw ValidationException::withMessages([
                    'pin_code' => ['Invalid or missing PIN code.'],
                ]);
            }
        } 
        // Cas 2 : Nouveau compte ➔ Création possible même si PIN vide
        else {
            $account = Account::create([
                'phone' => $data['phone'],
                'pin_code' => !empty($data['pin_code']) ? bcrypt($data['pin_code']) : null,
                'phone_uuid' => $data['phone_uuid'] ?? null,
                'needs_assistance' => $data['needs_assistance'] ?? false,
            ]);
        }

        // Vérification de l'état du compte
        if (!$account->is_active) {
            throw ValidationException::withMessages([
                'account' => ['This account is inactive.'],
            ]);
        }

        // Mise à jour de l'UUID si besoin
        if (!empty($data['phone_uuid']) && $account->phone_uuid !== $data['phone_uuid']) {
            $account->phone_uuid = $data['phone_uuid'];
        }

        $account->last_login_at = now();
        $account->save();

        $token = $account->createToken('account-token')->plainTextToken;

        return [
            'token' => $token,
            'account' => $account,
        ];
    }
}
// Compare this snippet from app/Models/Account.php: