<?php

namespace App\Domains\Identity\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IdentityPinAttempt extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'identity_id',
        'device_id',
        'success',
    ];

    protected $casts = [
        'success' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (IdentityPinAttempt $attempt): void {
            if (empty($attempt->id)) {
                $attempt->id = (string) Str::uuid();
            }
        });
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(Identity::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(IdentityDevice::class, 'device_id');
    }
}
