<?php

namespace App\Domains\Ambassador\Domain\Repositories;

use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Referral;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\AmbassadorReferralUsage;
use DateTimeInterface;

interface ReferralRepository
{
    public function create(Ambassador $ambassador, array $data): Referral;

    public function revoke(Referral $referral): Referral;

    public function addUsage(Referral $referral, string $eventType, ?string $source, DateTimeInterface $occurredAt): AmbassadorReferralUsage;
}
