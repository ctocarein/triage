<?php

namespace App\Domains\Ambassador\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmbassadorBadge extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'ambassador_badges';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'ambassador_id',
        'badge_code',
        'status',
        'earned_at',
        'revoked_at',
        'evidence',
    ];

    protected $casts = [
        'earned_at' => 'datetime',
        'revoked_at' => 'datetime',
        'evidence' => 'array',
    ];

    public function ambassador(): BelongsTo
    {
        return $this->belongsTo(Ambassador::class, 'ambassador_id');
    }
}
