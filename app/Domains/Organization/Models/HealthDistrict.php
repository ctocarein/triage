<?php

namespace App\Domains\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A health district -- the peripheral level of the health pyramid, and the
 * routing key of the platform.
 *
 * `region_assignment_verified` is false on every row as seeded: the source
 * rebuilt the district -> region assignment from the administrative map rather
 * than taking it from an official table. Aggregating by region is therefore
 * indicative until the DIIS validates it; aggregating by district is not
 * affected, the district names being official.
 */
class HealthDistrict extends Model
{
    protected $fillable = [
        'health_region_id',
        'code',
        'slug',
        'name',
        'seat',
        'latitude',
        'longitude',
        'region_assignment_verified',
        'assignment_note',
    ];

    protected $casts = [
        'latitude'                   => 'float',
        'longitude'                  => 'float',
        'region_assignment_verified' => 'boolean',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(HealthRegion::class, 'health_region_id');
    }

    /**
     * True when the source flagged the region attachment as contested.
     *
     * Three districts are concerned -- Nassian, Kaniasso,
     * Kouassi-Kouassikro -- and their note says where else they are listed.
     */
    public function hasContestedAssignment(): bool
    {
        return $this->assignment_note !== null;
    }
}
