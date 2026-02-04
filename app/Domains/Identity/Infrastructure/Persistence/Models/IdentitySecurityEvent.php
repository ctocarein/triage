<?php

namespace App\Domains\Identity\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IdentitySecurityEvent extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'identity_id',
        'type',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (IdentitySecurityEvent $event): void {
            if (empty($event->id)) {
                $event->id = (string) Str::uuid();
            }
        });
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(Identity::class);
    }
}
