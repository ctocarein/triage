<?php

namespace App\Domains\Identity\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credential extends Model
{
    protected $fillable = [
        'identity_id',
        'type',
        'identifier',
        'secret_hash',
        'verified_at',
        'last_used_at',
        'must_change_password',
    ];

    protected $casts = [
        'verified_at'          => 'datetime',
        'last_used_at'         => 'datetime',
        'must_change_password' => 'boolean',
    ];

    protected $hidden = [
        'secret_hash',
    ];

    public function identity(): BelongsTo
    {
        return $this->belongsTo(Identity::class);
    }
}
