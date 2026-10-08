<?php

namespace Database\Seeders\Identity;

use App\Domains\Access\Models\Membership;
use App\Domains\Identity\Infrastructure\Persistence\Models\Credential;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;
use App\Domains\Profile\Infrastructure\Persistence\Models\PersonalProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Une ambassadrice prete a se connecter depuis l'application mobile :
 * numero de telephone et PIN deja poses, role Ambassador actif.
 *
 * Volontairement hors de DatabaseSeeder — on la cree a la demande :
 *   php artisan db:seed --class="Database\Seeders\Identity\AmbassadorSeeder"
 *
 * Elle est rattachee a l'organisation ou atterrissent les beneficiaires
 * inscrites par telephone : l'evaluation assistee exige que l'evaluatrice et
 * la beneficiaire partagent une organisation.
 *
 * Relancer le seeder remet le PIN a la valeur configuree.
 */
class AmbassadorSeeder extends Seeder
{
    public function run(): void
    {
        $phone = env('SEED_AMBASSADOR_PHONE');
        $pin = env('SEED_AMBASSADOR_PIN');

        // En production, pas d'identifiants connus de tous : ils doivent etre fournis.
        if (app()->environment('production') && (! $phone || ! $pin)) {
            throw new RuntimeException(
                'SEED_AMBASSADOR_PHONE et SEED_AMBASSADOR_PIN sont requis en production.'
            );
        }

        $phone ??= '+2250700000001';
        $pin ??= '1234';

        $organization = $this->platformOrganization();

        DB::transaction(function () use ($phone, $pin, $organization) {
            $credential = Credential::where('type', 'phone')
                ->where('identifier', $phone)
                ->first();

            if ($credential) {
                $identity = $credential->identity;
            } else {
                $identity = Identity::create(['kind' => 'person', 'status' => 'active']);

                $credential = Credential::create([
                    'identity_id' => $identity->id,
                    'type' => 'phone',
                    'identifier' => $phone,
                    'verified_at' => now(),
                ]);
            }

            $credential->forceFill(['secret_hash' => Hash::make($pin)])->save();

            PersonalProfile::firstOrCreate(
                ['identity_id' => $identity->id],
                ['first_name' => 'Ambassadrice', 'last_name' => 'Demo'],
            );

            Membership::updateOrCreate([
                'identity_id' => $identity->id,
                'organization_id' => $organization->id,
                'role' => 'Ambassador',
            ], [
                'status' => 'active',
            ]);
        });

        $this->command?->info("Ambassadrice prete : {$phone} (organisation {$organization->name}).");
    }

    /**
     * Meme resolution que l'inscription par telephone : l'organisation portee
     * par un PlatformAdmin actif, a defaut l'organisation plateforme par son nom.
     */
    private function platformOrganization(): Organization
    {
        $organization = Organization::query()
            ->whereHas('memberships', function ($query) {
                $query->where('role', 'PlatformAdmin')
                    ->where('status', 'active');
            })
            ->first()
            ?? Organization::whereIn('name', ["Care'IN triage", 'CareIN'])->first();

        if (! $organization) {
            throw new RuntimeException(
                "Organisation plateforme introuvable : lancer d'abord IdentitySeeder."
            );
        }

        return $organization;
    }
}
