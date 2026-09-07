<?php

namespace App\Domains\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One learnt pair: a place as a woman names it, and the health district a
 * professional attached her to.
 */
class LocationAlias extends Model
{
    protected $fillable = [
        'normalized_key',
        'raw_label',
        'source',
        'health_district_id',
        'occurrences',
        'confirmed_at',
    ];

    protected $casts = [
        'occurrences'  => 'integer',
        'confirmed_at' => 'datetime',
    ];

    public function healthDistrict(): BelongsTo
    {
        return $this->belongsTo(HealthDistrict::class, 'health_district_id');
    }

    /** A human vouched for this mapping. */
    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }
}
