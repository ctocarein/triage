<?php

namespace App\Domains\Organization\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A health region of the national health map -- the intermediate level of the
 * health pyramid, between the PRES and the districts.
 */
class HealthRegion extends Model
{
    protected $fillable = [
        'code',
        'slug',
        'name',
        'seat',
        'pres',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    public function districts(): HasMany
    {
        return $this->hasMany(HealthDistrict::class);
    }
}
