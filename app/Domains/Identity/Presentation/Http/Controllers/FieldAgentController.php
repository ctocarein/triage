<?php

namespace App\Domains\Identity\Presentation\Http\Controllers;

use App\Domains\Access\Models\Membership;
use App\Domains\Identity\Infrastructure\Persistence\Models\Credential;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FieldAgentController extends Controller
{
    /**
     * POST /agent/beneficiaries
     * Permet à un FieldAgent de créer ou retrouver un Bénéficiaire via son numéro de téléphone.
     * Retourne les données d'identité du bénéficiaire (sans token).
     */
    /**
     * POST /agent/qr-scan
     * Permet à un FieldAgent de scanner le QR d'un bénéficiaire (chereh://identity/{id}).
     * Retourne les infos du bénéficiaire et le lie à l'organisation de l'agent si besoin.
     */
    public function scanQr(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor instanceof Identity || ! $actor->hasMembershipRole('FieldAgent')) {
            abort(403, 'Accès réservé aux agents de terrain.');
        }

        $orgId = $actor->memberships()
            ->where('role', 'FieldAgent')
            ->where('status', 'active')
            ->value('organization_id');

        if (! $orgId) {
            abort(403, 'Aucune organisation active trouvée pour cet agent.');
        }

        $data = $request->validate([
            'identity_id' => ['required', 'string', 'uuid'],
        ]);

        $identity = Identity::find($data['identity_id']);

        if (! $identity) {
            return response()->json(['message' => 'Bénéficiaire introuvable.'], 404);
        }

        // Seuls les bénéficiaires peuvent être scannés par un agent
        $isBeneficiary = $identity->memberships()
            ->where('role', 'Beneficiary')
            ->exists();

        if (! $isBeneficiary) {
            return response()->json([
                'message' => 'Ce QR code ne correspond pas à un bénéficiaire.',
            ], 422);
        }

        // Lier à l'organisation de l'agent si pas encore fait
        $isLinked = $identity->memberships()
            ->where('role', 'Beneficiary')
            ->where('organization_id', $orgId)
            ->exists();

        if (! $isLinked) {
            Membership::create([
                'identity_id'     => $identity->id,
                'organization_id' => $orgId,
                'role'            => 'Beneficiary',
                'status'          => 'active',
            ]);
        }

        $phone = $identity->credentials()
            ->where('type', 'phone')
            ->value('identifier');

        return response()->json([
            'identity_id' => (string) $identity->id,
            'phone'       => $phone ?? '',
            'is_linked'   => $isLinked,
        ]);
    }

    /**
     * POST /agent/beneficiaries
     * Permet à un FieldAgent de créer ou retrouver un Bénéficiaire via son numéro de téléphone.
     * Retourne les données d'identité du bénéficiaire (sans token).
     */
    public function createBeneficiary(Request $request): JsonResponse
    {
        $actor = $request->user();

        if (! $actor instanceof Identity || ! $actor->hasMembershipRole('FieldAgent')) {
            abort(403, 'Accès réservé aux agents de terrain.');
        }

        $orgId = $actor->memberships()
            ->where('role', 'FieldAgent')
            ->where('status', 'active')
            ->value('organization_id');

        if (! $orgId) {
            abort(403, 'Aucune organisation active trouvée pour cet agent.');
        }

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $phone = $data['phone'];

        // Chercher une identité existante avec ce numéro
        $existing = Credential::where('type', 'phone')
            ->where('identifier', $phone)
            ->first();

        if ($existing) {
            $identity = $existing->identity;

            // S'assurer qu'elle a un membership Beneficiary dans l'organisation de l'agent
            $hasMembership = $identity->memberships()
                ->where('role', 'Beneficiary')
                ->where('organization_id', $orgId)
                ->exists();

            if (! $hasMembership) {
                Membership::create([
                    'identity_id'     => $identity->id,
                    'organization_id' => $orgId,
                    'role'            => 'Beneficiary',
                    'status'          => 'active',
                ]);
            }

            return response()->json([
                'identity_id' => (string) $identity->id,
                'phone'       => $phone,
                'is_new'      => false,
            ]);
        }

        // Créer une nouvelle identité Bénéficiaire
        $identity = DB::transaction(function () use ($phone, $orgId) {
            $identity = Identity::create(['kind' => 'person', 'status' => 'active']);

            Credential::create([
                'identity_id' => $identity->id,
                'type'        => 'phone',
                'identifier'  => $phone,
                'secret_hash' => null,
                'verified_at' => now(),
            ]);

            Membership::create([
                'identity_id'     => $identity->id,
                'organization_id' => $orgId,
                'role'            => 'Beneficiary',
                'status'          => 'active',
            ]);

            return $identity;
        });

        return response()->json([
            'identity_id' => (string) $identity->id,
            'phone'       => $phone,
            'is_new'      => true,
        ], 201);
    }
}
