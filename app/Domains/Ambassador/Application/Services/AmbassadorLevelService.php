<?php

namespace App\Domains\Ambassador\Application\Services;

use App\Domains\Ambassador\Domain\Events\AmbassadorLevelChanged;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\Ambassador;
use App\Domains\Ambassador\Infrastructure\Persistence\Models\AmbassadorLevel;

class AmbassadorLevelService
{
    /**
     * @return array<string, mixed>
     */
    public function ensureStarterLevel(Ambassador $ambassador): array
    {
        $level = AmbassadorLevel::query()->firstOrNew(['ambassador_id' => $ambassador->id]);
        if (! $level->exists) {
            $level->forceFill([
                'level_code' => 'starter',
                'computed_at' => now(),
            ])->save();
        }

        return $level->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function getCurrentLevel(Ambassador $ambassador): array
    {
        $level = AmbassadorLevel::query()->where('ambassador_id', $ambassador->id)->first();

        if ($level === null) {
            return [
                'ambassador_id' => $ambassador->id,
                'level_code' => 'starter',
                'computed_at' => null,
            ];
        }

        return $level->toArray();
    }

    /**
     * @param array<string, mixed> $metrics
     * @return array<string, mixed>
     */
    public function recomputeLevel(Ambassador $ambassador, array $metrics): array
    {
        $completed = (int) ($metrics['completed_usages'] ?? 0);
        $levelCode = 'starter';

        if ($completed >= 2000) {
            $levelCode = 'golden';
        } elseif ($completed >= 1000) {
            $levelCode = 'leader';
        } elseif ($completed >= 200) {
            $levelCode = 'active';
        } else {
            $levelCode = 'starter';
        }

        $level = AmbassadorLevel::query()->firstOrNew(['ambassador_id' => $ambassador->id]);
        $level->forceFill([
            'level_code' => $levelCode,
            'computed_at' => now(),
        ])->save();

        event(new AmbassadorLevelChanged($ambassador->id, $levelCode));

        return $level->toArray();
    }
}
