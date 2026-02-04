<?php

namespace App\Domains\Profile\Application\Services;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Profile\Infrastructure\Persistence\Models\ProfessionalProfile;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProfessionalProfileService
{
    public function get(Identity $identity): ?ProfessionalProfile
    {
        return $identity->professionalProfile;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Identity $identity, array $data): ProfessionalProfile
    {
        if ($identity->professionalProfile) {
            throw new HttpException(409, 'Professional profile already exists. Please use update instead.');
        }

        $profile = new ProfessionalProfile($data);
        $profile->identity()->associate($identity);
        $profile->save();

        return $profile->fresh();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Identity $identity, array $data): ProfessionalProfile
    {
        $profile = $identity->professionalProfile;

        if (! $profile) {
            throw new HttpException(404, 'No professional profile found.');
        }

        $profile->fill($data);
        $profile->save();

        return $profile->fresh();
    }
}
