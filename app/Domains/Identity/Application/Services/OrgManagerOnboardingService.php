<?php

namespace App\Domains\Identity\Application\Services;

use App\Domains\Access\Models\Membership;
use App\Domains\Identity\Infrastructure\Persistence\Models\Credential;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Identity\Infrastructure\Persistence\Models\OrgManagerOnboardingRequest;
use App\Domains\Organization\Models\Organization;
use App\Notifications\Identity\OrgManagerApprovedNotification;
use App\Notifications\Identity\OrgManagerEmailVerificationNotification;
use App\Notifications\Identity\OrgManagerPendingAdminNotification;
use App\Notifications\Identity\OrgManagerRejectedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrgManagerOnboardingService
{
    private const TOKEN_LENGTH = 64;

    /**
     * Enregistre une demande d'onboarding OrgManager.
     * Crée identity (email_unverified) + credential + membership (pending) + request.
     * Envoie l'email de vérification.
     */
    public function register(array $data): array
    {
        $email = $data['email'];

        $existing = Credential::where('type', 'email')
            ->where('identifier', $email)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'email' => ['Cette adresse email est déjà utilisée.'],
            ]);
        }

        $token = $this->generateToken();

        DB::transaction(function () use ($email, $data, $token) {
            // Créer l'organisation à partir des données du formulaire
            $organization = Organization::create([
                'type'    => $data['org_type'],
                'name'    => $data['org_name'],
                'country' => $data['org_country'],
                'city'    => $data['org_city'],
                'address' => $data['org_address'] ?? null,
                'status'  => 'pending',
            ]);

            $identity = Identity::create([
                'kind'   => 'person',
                'status' => 'email_unverified',
            ]);

            Credential::create([
                'identity_id' => $identity->id,
                'type'        => 'email',
                'identifier'  => $email,
                'secret_hash' => Hash::make($data['password']),
                'verified_at' => null,
            ]);

            Membership::create([
                'identity_id'     => $identity->id,
                'organization_id' => $organization->id,
                'role'            => 'OrgManager',
                'status'          => 'pending',
            ]);

            OrgManagerOnboardingRequest::create([
                'identity_id'                => $identity->id,
                'organization_id'            => $organization->id,
                'email_verification_token'   => $token,
                'email_verification_sent_at' => now(),
                'attempt_number'             => 1,
                'status'                     => 'email_unverified',
            ]);
        });

        // Envoi email hors transaction pour ne pas bloquer en cas d'échec SMTP
        Notification::route('mail', $email)
            ->notify(new OrgManagerEmailVerificationNotification($token));

        return [
            'message' => 'Demande créée. Vérifiez votre email pour confirmer votre adresse.',
        ];
    }

    /**
     * Vérifie le token email et passe la demande à pending_validation.
     * Notifie les PlatformAdmins.
     */
    public function verifyEmail(string $token): array
    {
        $request = OrgManagerOnboardingRequest::where('email_verification_token', $token)
            ->where('status', 'email_unverified')
            ->first();

        if (! $request) {
            abort(404, 'Token de vérification invalide ou expiré.');
        }

        $identity = $request->identity;

        DB::transaction(function () use ($identity, $request) {
            $identity->forceFill(['status' => 'pending_validation'])->save();

            $identity->credentials()
                ->where('type', 'email')
                ->update(['verified_at' => now()]);

            $request->forceFill([
                'status'                   => 'pending_validation',
                'email_verification_token' => null,
                'admin_notified_at'        => now(),
            ])->save();
        });

        // Notification admins hors transaction
        $this->notifyPlatformAdmins($request->fresh());

        return [
            'message' => 'Email vérifié. Votre demande est en attente de validation par un administrateur.',
        ];
    }

    /**
     * Approuve une demande d'onboarding.
     * Active identity + membership. Notifie le candidat.
     */
    public function approve(OrgManagerOnboardingRequest $onboardingRequest): void
    {
        if (! $onboardingRequest->isPendingValidation()) {
            abort(422, 'Cette demande ne peut pas être approuvée dans son état actuel.');
        }

        $identity = $onboardingRequest->identity;
        $email = $this->resolveEmail($identity);

        DB::transaction(function () use ($identity, $onboardingRequest) {
            $identity->forceFill(['status' => 'active'])->save();

            Membership::where('identity_id', $identity->id)
                ->where('organization_id', $onboardingRequest->organization_id)
                ->where('role', 'OrgManager')
                ->update(['status' => 'active']);

            $onboardingRequest->forceFill(['status' => 'approved'])->save();
        });

        if ($email) {
            Notification::route('mail', $email)
                ->notify(new OrgManagerApprovedNotification($onboardingRequest->organization));
        }
    }

    /**
     * Rejette une demande d'onboarding.
     * Passe identity + membership en rejected. Notifie le candidat.
     */
    public function reject(OrgManagerOnboardingRequest $onboardingRequest, string $reasonCode, ?string $reasonDetail): void
    {
        if (! $onboardingRequest->isPendingValidation()) {
            abort(422, 'Cette demande ne peut pas être rejetée dans son état actuel.');
        }

        $identity = $onboardingRequest->identity;
        $email = $this->resolveEmail($identity);

        DB::transaction(function () use ($identity, $onboardingRequest, $reasonCode, $reasonDetail) {
            $identity->forceFill(['status' => 'rejected'])->save();

            Membership::where('identity_id', $identity->id)
                ->where('organization_id', $onboardingRequest->organization_id)
                ->where('role', 'OrgManager')
                ->update(['status' => 'rejected']);

            $onboardingRequest->forceFill([
                'status'                 => 'rejected',
                'rejected_reason_code'   => $reasonCode,
                'rejected_reason_detail' => $reasonDetail,
            ])->save();
        });

        if ($email) {
            Notification::route('mail', $email)
                ->notify(new OrgManagerRejectedNotification($reasonCode, $reasonDetail));
        }
    }

    /**
     * Envoie des rappels aux PlatformAdmins pour les demandes en attente.
     * Appelé par le job schedulé (J+2, J+5, J+7 après admin_notified_at).
     *
     * @return int Nombre de rappels envoyés
     */
    public function sendAdminReminders(): int
    {
        $sent = 0;

        $pending = OrgManagerOnboardingRequest::where('status', 'pending_validation')
            ->whereNotNull('admin_notified_at')
            ->where('reminder_count', '<', 3)
            ->get();

        foreach ($pending as $request) {
            $hoursSinceNotified = now()->diffInHours($request->admin_notified_at);
            $count = $request->reminder_count;

            $shouldSend = match ($count) {
                0 => $hoursSinceNotified >= 48,
                1 => $hoursSinceNotified >= 120,
                2 => $hoursSinceNotified >= 168,
                default => false,
            };

            if (! $shouldSend) {
                continue;
            }

            $this->notifyPlatformAdmins($request);

            $request->forceFill([
                'last_reminder_at' => now(),
                'reminder_count'   => $count + 1,
            ])->save();

            $sent++;
        }

        return $sent;
    }

    private function notifyPlatformAdmins(OrgManagerOnboardingRequest $request): void
    {
        $admins = Identity::whereHas('memberships', function ($q) {
            $q->where('role', 'PlatformAdmin')->where('status', 'active');
        })->with('credentials')->get();

        foreach ($admins as $admin) {
            $email = $this->resolveEmail($admin);
            if ($email) {
                Notification::route('mail', $email)
                    ->notify(new OrgManagerPendingAdminNotification($request));
            }
        }
    }

    private function resolveEmail(Identity $identity): ?string
    {
        return $identity->credentials()
            ->where('type', 'email')
            ->value('identifier');
    }

    private function generateToken(): string
    {
        return Str::random(self::TOKEN_LENGTH);
    }
}
