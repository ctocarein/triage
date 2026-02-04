<?php

namespace App\Domains\Identity\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IdentityDevice extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'identity_id',
        'device_fingerprint',
        'trusted_at',
        'last_seen_at',
        'revoked_at',
    ];

    protected $casts = [
        'trusted_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (IdentityDevice $device): void {
            if (empty($device->id)) {
                $device->id = (string) Str::uuid();
            }
        });
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(Identity::class);
    }
}
