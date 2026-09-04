<?php

namespace App\Domains\Identity\Application\Services;

use App\Domains\Access\Models\Membership;
use App\Domains\Evaluation\Domain\Policies\IdentityEvaluationPolicy;
use App\Domains\Identity\Infrastructure\Persistence\Models\Credential;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Enrolement d'un beneficiaire par un evaluateur (agent de terrain ou ambassadeur).
 *
 * Partage entre l'enrolement explicite (POST /agent/beneficiaries) et l'enrolement
 * implicite au demarrage d'une evaluation, pour que les deux chemins produisent
 * exactement la meme identite : une Identity, une Credential telephone verifiee,
 * un Membership Beneficiary actif. Les profils personnel et medical ne sont pas
 * crees ici : ils sont alimentes par ProfileUpdaterService pendant l'evaluation.
 */
class BeneficiaryEnrollmentService
{
    /**
     * Organisation de rattachement pour un beneficiaire enrole par cet acteur.
     *
     * L'acteur qui n'appartient a aucune organisation opere pour le compte de
     * l'organisation par defaut : on l'y rattache explicitement plutot que de
     * laisser la policy deviner, sans quoi il ne pourrait pas evaluer la
     * personne qu'il vient lui-meme d'enroler.
     */
    public function resolveEnrollmentOrganization(Identity $actor): Organization
    {
        $membership = $actor->memberships()
            ->where('status', 'active')
            ->whereIn('role', IdentityEvaluationPolicy::EVALUATOR_ROLES)
            ->whereNotNull('organization_id')
            ->first();

        if ($membership) {
            return Organization::findOrFail($membership->organization_id);
        }

        $organization = $this->defaultOrganization();
        $this->attachActorToDefaultOrganization($actor, $organization);

        return $organization;
    }

    /**
     * Retrouve ou cree le beneficiaire porteur de ce numero, et s'assure qu'il
     * est membre actif de l'organisation de rattachement.
     *
     * @return array{identity: Identity, is_new: bool}
     */
    public function enroll(Identity $actor, string $phone): array
    {
        $organization = $this->resolveEnrollmentOrganization($actor);

        $existing = Credential::query()
            ->where('type', 'phone')
            ->where('identifier', $phone)
            ->first();

        if ($existing && $existing->identity) {
            $identity = $existing->identity;
            $this->ensureBeneficiaryMembership($identity, $organization);

            return ['identity' => $identity, 'is_new' => false];
        }

        $identity = DB::transaction(function () use ($phone, $organization): Identity {
            $identity = Identity::create(['kind' => 'person', 'status' => 'active']);

            Credential::create([
                'identity_id' => $identity->id,
                'type'        => 'phone',
                'identifier'  => $phone,
                'secret_hash' => null,
                'verified_at' => now(),
            ]);

            $this->ensureBeneficiaryMembership($identity, $organization);

            return $identity;
        });

        return ['identity' => $identity, 'is_new' => true];
    }

    /**
     * Rattache un numero a un beneficiaire, collecte pendant l'evaluation.
     *
     * Le numero est la cle de dedoublonnage : s'il est deja porte par une autre
     * identite, c'est que la personne est deja connue et que l'identite creee au
     * demarrage est un doublon. On bascule alors sur l'identite existante plutot
     * que de fusionner apres coup, ce qui n'est tenable que parce que la question
     * est posee tot, quand presque rien n'a encore ete ecrit.
     *
     * @return array{identity: Identity, reconciled: bool, pin: string|null}
     */
    public function attachPhone(Identity $subject, string $phone): array
    {
        $existing = Credential::query()
            ->where('type', 'phone')
            ->where('identifier', $phone)
            ->first();

        if ($existing && (string) $existing->identity_id !== (string) $subject->id) {
            $known = $existing->identity;

            if ($known) {
                return ['identity' => $known, 'reconciled' => true, 'pin' => null];
            }
        }

        if ($existing) {
            // Meme personne : la question a simplement ete re-repondue.
            return ['identity' => $subject, 'reconciled' => false, 'pin' => null];
        }

        $own = $subject->credentials()->where('type', 'phone')->first();

        if ($own) {
            // L'evaluateur corrige un numero saisi plus tot dans la meme visite :
            // on ne retouche pas le PIN deja attribue.
            $own->forceFill(['identifier' => $phone])->save();

            return ['identity' => $subject, 'reconciled' => false, 'pin' => null];
        }

        $pin = (string) config('identity.default_pin');

        Credential::create([
            'identity_id'          => $subject->id,
            'type'                 => 'phone',
            'identifier'           => $phone,
            'secret_hash'          => Hash::make($pin),
            'verified_at'          => now(),
            'must_change_password' => true,
        ]);

        return ['identity' => $subject, 'reconciled' => false, 'pin' => $pin];
    }

    /**
     * Neutralise l'identite creee au demarrage devenue orpheline apres une
     * reconciliation. On ne supprime pas : le statut anonymized suffit a la
     * sortir des parcours sans casser d'eventuelles references.
     */
    public function retireDuplicate(Identity $duplicate): void
    {
        if ($duplicate->credentials()->exists() || $duplicate->evaluationSessions()->exists()) {
            return;
        }

        $duplicate->forceFill(['status' => 'anonymized'])->save();
        $duplicate->memberships()->update(['status' => 'inactive']);
    }

    /**
     * Cree un beneficiaire pas encore identifie, pour une evaluation qui demarre
     * sans savoir a qui elle s'adresse. Le numero et le PIN viendront de la
     * question dediee du questionnaire, via attachPhone().
     */
    public function createAnonymousBeneficiary(Identity $actor): Identity
    {
        $organization = $this->resolveEnrollmentOrganization($actor);

        return DB::transaction(function () use ($organization): Identity {
            $identity = Identity::create(['kind' => 'person', 'status' => 'active']);
            $this->ensureBeneficiaryMembership($identity, $organization);

            return $identity;
        });
    }

    public function defaultOrganization(): Organization
    {
        $config = config('identity.default_organization');

        return Organization::firstOrCreate(
            ['id' => $config['id']],
            [
                'name'   => $config['name'],
                'type'   => $config['type'] ?? 'platform',
                'status' => 'active',
            ]
        );
    }

    protected function ensureBeneficiaryMembership(Identity $identity, Organization $organization): void
    {
        Membership::firstOrCreate(
            [
                'identity_id'     => $identity->id,
                'organization_id' => $organization->id,
                'role'            => 'Beneficiary',
            ],
            ['status' => 'active']
        );
    }

    protected function attachActorToDefaultOrganization(Identity $actor, Organization $organization): void
    {
        $role = $actor->memberships()
            ->where('status', 'active')
            ->whereIn('role', IdentityEvaluationPolicy::EVALUATOR_ROLES)
            ->value('role');

        if ($role === null) {
            return;
        }

        Membership::firstOrCreate(
            [
                'identity_id'     => $actor->id,
                'organization_id' => $organization->id,
                'role'            => $role,
            ],
            ['status' => 'active']
        );
    }
}
