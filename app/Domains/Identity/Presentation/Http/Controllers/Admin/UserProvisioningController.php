<?php

namespace App\Domains\Identity\Presentation\Http\Controllers\Admin;

use App\Domains\Access\Models\Membership;
use App\Domains\Identity\Infrastructure\Persistence\Models\Credential;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Notifications\Identity\PractitionerInviteNotification;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserProvisioningController extends Controller
{
    /**
     * POST /admin/users/practitioner
     * Creates a Practitioner identity with email credential + temp password.
     * Sends an invite email with the temporary password.
     */
    public function createPractitioner(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403);
        }

        $orgId = $actor->memberships()
            ->where('role', 'OrgManager')
            ->value('organization_id');

        if (! $orgId) {
            abort(403, 'Vous n\'êtes pas responsable d\'une organisation.');
        }

        $data = $request->validate([
            'email'      => ['required', 'email', 'max:255'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name'  => ['sometimes', 'string', 'max:100'],
        ]);

        $existing = Credential::where('type', 'email')
            ->where('identifier', $data['email'])
            ->first();
        if ($existing) {
            throw ValidationException::withMessages([
                'email' => ['Cette adresse email est déjà utilisée.'],
            ]);
        }

        $tempPassword = Str::random(12);

        $result = DB::transaction(function () use ($data, $orgId, $tempPassword) {
            $identity = Identity::create(['kind' => 'person', 'status' => 'active']);

            Credential::create([
                'identity_id'          => $identity->id,
                'type'                 => 'email',
                'identifier'           => $data['email'],
                'secret_hash'          => Hash::make($tempPassword),
                'verified_at'          => now(),
                'must_change_password' => true,
            ]);

            Membership::create([
                'identity_id'     => $identity->id,
                'organization_id' => $orgId,
                'role'            => 'Practitioner',
                'status'          => 'active',
            ]);

            return $identity;
        });

        // Send invite email
        $result->notify(new PractitionerInviteNotification(
            orgName: $request->user()->memberships()->where('role', 'OrgManager')->with('organization')->first()?->organization?->name ?? 'Chereh',
            tempPassword: $tempPassword,
        ));

        return response()->json([
            'message' => 'Praticien créé. Un email avec les identifiants temporaires a été envoyé.',
            'identity_id' => $result->id,
            'email' => $data['email'],
        ], 201);
    }

    /**
     * POST /admin/users/field-agent
     * Creates a FieldAgent identity with phone credential (no password).
     * FieldAgents use the mobile app (my.chereh.com), not this dashboard.
     */
    public function createFieldAgent(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403);
        }

        $orgId = $actor->memberships()
            ->where('role', 'OrgManager')
            ->value('organization_id');

        if (! $orgId) {
            abort(403, 'Vous n\'êtes pas responsable d\'une organisation.');
        }

        $data = $request->validate([
            'phone'      => ['required', 'string', 'max:30'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name'  => ['sometimes', 'string', 'max:100'],
        ]);

        $existing = Credential::where('type', 'phone')
            ->where('identifier', $data['phone'])
            ->first();
        if ($existing) {
            throw ValidationException::withMessages([
                'phone' => ['Ce numéro de téléphone est déjà utilisé.'],
            ]);
        }

        $result = DB::transaction(function () use ($data, $orgId) {
            $identity = Identity::create(['kind' => 'person', 'status' => 'active']);

            Credential::create([
                'identity_id' => $identity->id,
                'type'        => 'phone',
                'identifier'  => $data['phone'],
                'secret_hash' => null,
                'verified_at' => now(),
            ]);

            Membership::create([
                'identity_id'     => $identity->id,
                'organization_id' => $orgId,
                'role'            => 'FieldAgent',
                'status'          => 'active',
            ]);

            return $identity;
        });

        return response()->json([
            'message'     => 'Agent de terrain créé avec succès.',
            'identity_id' => $result->id,
            'phone'       => $data['phone'],
        ], 201);
    }

    /**
     * PATCH /admin/users/change-password
     * Allows a Practitioner to change their email credential password on first login.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof Identity) {
            abort(403);
        }

        $data = $request->validate([
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $credential = $actor->credentials()
            ->where('type', 'email')
            ->first();

        if (! $credential) {
            return response()->json(['message' => 'Aucun credential email trouvé.'], 404);
        }

        $credential->forceFill([
            'secret_hash'          => Hash::make($data['new_password']),
            'must_change_password' => false,
        ])->save();

        return response()->json(['message' => 'Mot de passe mis à jour avec succès.']);
    }
}
