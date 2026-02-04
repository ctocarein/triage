<?php

namespace App\Domains\Identity\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IdentitySecurityGate extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'identity_id',
        'state',
        'unlocked_at',
        'locked_until',
        'panic_locked_at',
        'last_unlock_method',
    ];

    protected $casts = [
        'unlocked_at' => 'datetime',
        'locked_until' => 'datetime',
        'panic_locked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (IdentitySecurityGate $gate): void {
            if (empty($gate->id)) {
                $gate->id = (string) Str::uuid();
            }
        });
    }

    public function identity(): BelongsTo
    {
        return $this->belongsTo(Identity::class);
    }
}
