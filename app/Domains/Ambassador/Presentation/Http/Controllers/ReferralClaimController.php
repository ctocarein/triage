<?php

namespace App\Domains\Ambassador\Presentation\Http\Controllers;

use App\Domains\Ambassador\Infrastructure\Persistence\Models\PendingReferralClick;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Endpoints publics appelés par l'app mobile au premier lancement.
 *
 * POST /referral/claim            — Priorité 1/2/3 : token explicite (Install Referrer ou presse-papiers)
 * POST /referral/claim/fingerprint — Priorité 4   : empreinte IP + modèle appareil
 */
class ReferralClaimController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Claim par token (Install Referrer Android ou presse-papiers)
    // ─────────────────────────────────────────────────────────────────────────

    public function claimByToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'click_token'  => ['required', 'string', 'size:64'],
            'device_model' => ['nullable', 'string', 'max:128'],
        ]);

        $click = PendingReferralClick::query()
            ->with(['referral.ambassador.identity.personalProfile',
                    'referral.ambassador.identity.professionalProfile'])
            ->where('click_token', $data['click_token'])
            ->first();

        if (! $click) {
            return response()->json(['message' => 'Token introuvable.'], 404);
        }

        if ($click->isClaimed()) {
            return response()->json(['message' => 'Token déjà utilisé.'], 409);
        }

        if ($click->isExpired()) {
            return response()->json(['message' => 'Token expiré.'], 410);
        }

        // Anti-auto-parrainage : vérifie que le token n'appartient pas à l'ambassadrice elle-même
        // (vérification complète faite à la création de compte)

        $click->update(['claimed_at' => now()]);

        return response()->json($this->buildAmbassadorPayload($click));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Claim par fingerprint (fallback : IP + modèle appareil)
    // ─────────────────────────────────────────────────────────────────────────

    public function claimByFingerprint(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_model' => ['required', 'string', 'max:128'],
            'os_version'   => ['nullable', 'string', 'max:64'],
        ]);

        $ip          = $request->ip() ?? '0.0.0.0';
        $deviceModel = $data['device_model'];
        $fingerprint = PendingReferralClick::computeFingerprint($ip, $deviceModel);

        // Anti-fraude : max N claims par IP par jour
        $maxClaims = (int) config('ambassador.referrals.max_claims_per_ip_per_day', 3);
        $claimsToday = PendingReferralClick::query()
            ->where('ip_address', $ip)
            ->whereNotNull('claimed_at')
            ->whereDate('claimed_at', now()->toDateString())
            ->count();

        if ($claimsToday >= $maxClaims) {
            return response()->json(['message' => 'Limite journalière atteinte pour cette adresse IP.'], 429);
        }

        // Cherche un click non réclamé des dernières 24 h avec la même empreinte
        $click = PendingReferralClick::query()
            ->with(['referral.ambassador.identity.personalProfile',
                    'referral.ambassador.identity.professionalProfile'])
            ->where('device_fingerprint', $fingerprint)
            ->whereNull('claimed_at')
            ->where('expires_at', '>', now())
            ->where('created_at', '>=', now()->subHours(24))
            ->orderByDesc('created_at')
            ->first();

        if (! $click) {
            return response()->json(['message' => 'Aucune correspondance trouvée.'], 404);
        }

        $click->update(['claimed_at' => now()]);

        return response()->json($this->buildAmbassadorPayload($click));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Payload de réponse
    // ─────────────────────────────────────────────────────────────────────────

    private function buildAmbassadorPayload(PendingReferralClick $click): array
    {
        $referral   = $click->referral;
        $ambassador = $referral?->ambassador;
        $identity   = $ambassador?->identity;

        $fullName = trim((string) ($identity?->professionalProfile?->full_name ?? ''));
        if ($fullName === '') {
            $firstName = (string) ($identity?->personalProfile?->first_name ?? '');
            $lastName  = (string) ($identity?->personalProfile?->last_name ?? '');
            $fullName  = trim($firstName . ' ' . $lastName);
        }

        return [
            'ambassador' => [
                'id'   => $ambassador?->id,
                'name' => $fullName ?: null,
            ],
            'referral_code' => $referral?->code,
        ];
    }
}
