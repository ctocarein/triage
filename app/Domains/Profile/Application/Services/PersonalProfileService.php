<?php

namespace App\Domains\Profile\Application\Services;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Profile\Infrastructure\Persistence\Models\PersonalProfile;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PersonalProfileService
{
    public function get(Identity $identity): ?PersonalProfile
    {
        return $identity->personalProfile;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(Identity $identity, array $data): PersonalProfile
    {
        if ($identity->personalProfile) {
            throw new HttpException(409, 'Personal profile already exists. Please use update instead.');
        }

        $profile = new PersonalProfile($data);
        $profile->identity()->associate($identity);
        $profile->save();

        return $profile->fresh();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Identity $identity, array $data): PersonalProfile
    {
        $profile = $identity->personalProfile;

        if (! $profile) {
            throw new HttpException(404, 'No personal profile found.');
        }

        $profile->fill($data);
        $profile->save();

        return $profile->fresh();
    }

    public function delete(Identity $identity): void
    {
        $profile = $identity->personalProfile;

        if ($profile) {
            $profile->delete();
        }
    }
}
