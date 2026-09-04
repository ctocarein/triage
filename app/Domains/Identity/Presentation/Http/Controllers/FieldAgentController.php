<?php

namespace App\Domains\Identity\Presentation\Http\Controllers;

use App\Domains\Access\Models\Membership;
use App\Domains\Evaluation\Domain\Policies\IdentityEvaluationPolicy;
use App\Domains\Identity\Application\Services\BeneficiaryEnrollmentService;
use App\Domains\Identity\Infrastructure\Persistence\Models\Credential;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FieldAgentController extends Controller
{
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
     * Permet a un evaluateur (agent de terrain ou ambassadeur) de creer ou retrouver
     * un Beneficiaire via son numero de telephone.
     * Retourne les donnees d'identite du beneficiaire (sans token).
     */
    public function createBeneficiary(
        Request $request,
        BeneficiaryEnrollmentService $enrollment
    ): JsonResponse {
        $actor = $request->user();

        if (! $actor instanceof Identity || ! IdentityEvaluationPolicy::isEvaluator($actor)) {
            abort(403, "Acces reserve aux agents de terrain et aux ambassadeurs.");
        }

        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        // L'evaluateur sans organisation n'est plus refuse : son beneficiaire est
        // rattache a l'organisation par defaut, et lui-meme y est rattache pour
        // pouvoir ensuite l'evaluer.
        $result = $enrollment->enroll($actor, $data['phone']);

        return response()->json([
            'identity_id' => (string) $result['identity']->id,
            'phone'       => $data['phone'],
            'is_new'      => $result['is_new'],
        ], $result['is_new'] ? 201 : 200);
    }
}
