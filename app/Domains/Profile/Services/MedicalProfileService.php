<?php

namespace App\Domains\Profile\Services;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Profile\Models\MedicalProfile;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MedicalProfileService
{
    public function get(Identity $identity): ?MedicalProfile
    {
        return $identity->medicalProfile;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Identity $identity, array $data): MedicalProfile
    {
        if ($identity->medicalProfile) {
            throw new HttpException(409, 'Medical profile already exists. Use update instead.');
        }

        $profile = new MedicalProfile($data);
        $profile->identity()->associate($identity);
        $profile->save();

        return $profile->fresh();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Identity $identity, array $data): MedicalProfile
    {
        $profile = $identity->medicalProfile;

        if (! $profile) {
            throw new HttpException(404, 'No medical profile found.');
        }

        $profile->fill($data);
        $profile->save();

        return $profile->fresh();
    }
}
