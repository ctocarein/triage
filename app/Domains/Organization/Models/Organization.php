<?php

namespace App\Domains\Organization\Models;

use App\Domains\Access\Models\Membership;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'type',
        'name',
        'country',
        'city',
        'address',
        'health_district_id',
        'latitude',
        'longitude',
        'status',
        'plan',
    ];

    protected $casts = [
        'latitude'  => 'float',
        'longitude' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            if (empty($organization->id)) {
                $organization->id = (string) Str::uuid();
            }
        });
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'organization_id');
    }

    /**
     * The health district this facility belongs to.
     *
     * Null on rows that predate the health map: no truthful value could be
     * invented for them, and a wrong district sends a woman to the wrong
     * facility.
     */
    public function healthDistrict(): BelongsTo
    {
        return $this->belongsTo(HealthDistrict::class, 'health_district_id');
    }
}
