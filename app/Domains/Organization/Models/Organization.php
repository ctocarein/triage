<?php

namespace App\Domains\Organization\Models;

use App\Domains\Access\Models\Membership;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'status',
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
}
