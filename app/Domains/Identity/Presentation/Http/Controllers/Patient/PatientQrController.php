<?php

namespace App\Domains\Identity\Presentation\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Domains\Engagement\Infrastructure\Persistence\Models\QrCode;
use App\Domains\Access\Models\Membership;

class PatientQrController extends Controller
{
    /**
     * POST /qr/scan
     *
     * Le bénéficiaire soumet le code d'un QR organisation qu'il vient de scanner.
     * Si le QR est valide, la liaison bénéficiaire <-> organisation est créée dans
     * la table memberships (idempotent : déjà lié → pas de doublon).
     * L'événement de scan est toujours enregistré.
     */
    public function scan(Request $request)
    {
        $request->validate([
            'code'        => 'required|string|max:100',
            'device_info' => 'nullable|string|max:255',
            'latitude'    => 'nullable|numeric|between:-90,90',
            'longitude'   => 'nullable|numeric|between:-180,180',
        ]);

        $identityId = $request->user()->id;

        // ── Résoudre le QR code ──────────────────────────────────────
        $qr = QrCode::with('organization')
            ->where('code', $request->code)
            ->first();

        if (! $qr) {
            return response()->json([
                'message' => 'QR code introuvable.',
            ], 404);
        }

        if ($qr->status !== 'active') {
            $label = match ($qr->status) {
                'expired' => 'Ce QR code a expiré.',
                'revoked' => 'Ce QR code a été révoqué.',
                'used'    => 'Ce QR code a déjà été utilisé.',
                default   => 'Ce QR code n\'est plus actif.',
            };

            // Enregistrer le scan échoué
            $qr->scanEvents()->create([
                'identity_id' => $identityId,
                'scanned_at'  => now(),
                'device_info' => $request->input('device_info'),
                'ip_address'  => $request->ip(),
                'result'      => 'invalid',
            ]);

            return response()->json(['message' => $label], 422);
        }

        $org = $qr->organization;

        if (! $org) {
            return response()->json([
                'message' => 'Organisation introuvable pour ce QR code.',
            ], 422);
        }

        // ── Liaison bénéficiaire ↔ organisation (idempotent) ─────────
        $existing = Membership::where('identity_id', $identityId)
            ->where('organization_id', $org->id)
            ->where('role', 'Beneficiary')
            ->first();

        $alreadyLinked = $existing !== null;

        if (! $alreadyLinked) {
            Membership::create([
                'identity_id'     => $identityId,
                'organization_id' => $org->id,
                'role'            => 'Beneficiary',
                'status'          => 'active',
            ]);
        }

        // ── Enregistrement de l'événement de scan ────────────────────
        $location = null;
        if ($request->filled('latitude') && $request->filled('longitude')) {
            $location = [
                'latitude'  => $request->float('latitude'),
                'longitude' => $request->float('longitude'),
            ];
        }

        $qr->scanEvents()->create([
            'identity_id' => $identityId,
            'scanned_at'  => now(),
            'device_info' => $request->input('device_info'),
            'ip_address'  => $request->ip(),
            'location'    => $location,
            'result'      => 'success',
        ]);

        return response()->json([
            'organization' => [
                'id'   => $org->id,
                'name' => $org->name,
                'type' => $org->type,
                'city' => $org->city,
            ],
            'already_linked' => $alreadyLinked,
            'message'        => $alreadyLinked
                ? "Vous êtes déjà lié à {$org->name}."
                : "Vous êtes maintenant lié à {$org->name}\u{00a0}!",
        ]);
    }
}
