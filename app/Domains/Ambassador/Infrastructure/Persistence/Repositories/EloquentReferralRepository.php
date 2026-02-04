<?php

namespace App\Domains\Ambassador\Infrastructure\Persistence\Repositories;

use App\Domains\Ambassador\Domain\Events\ReferralCreated;
use App\Domains\Ambassador\Domain\Events\ReferralUsed;
use App\Domains\Ambassador\Domain\Repositories\ReferralRepository as ReferralRepositoryContract;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Referral;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\AmbassadorReferralUsage;
use DateTimeInterface;
use Illuminate\Support\Str;

class EloquentReferralRepository implements ReferralRepositoryContract
{
    public function create(Ambassador $ambassador, array $data): Referral
    {
        $code = $this->generateCode();

        $referral = Referral::query()->create([
            'ambassador_id' => $ambassador->id,
            'code' => $code,
            'channel' => $data['channel'],
            'expires_at' => $data['expires_at'],
        ]);

        event(new ReferralCreated($referral->id, $ambassador->id));

        return $referral;
    }

    public function revoke(Referral $referral): Referral
    {
        $referral->forceFill([
            'revoked_at' => now(),
        ])->save();

        return $referral;
    }

    public function addUsage(Referral $referral, string $eventType, ?string $source, DateTimeInterface $occurredAt): AmbassadorReferralUsage
    {
        $usage = AmbassadorReferralUsage::query()->create([
            'referral_id' => $referral->id,
            'event_type' => $eventType,
            'source' => $source,
            'occurred_at' => $occurredAt,
        ]);

        event(new ReferralUsed($referral->id, $eventType));

        return $usage;
    }

    protected function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(10));
        } while (Referral::query()->where('code', $code)->exists());

        return $code;
    }
}
