<?php

namespace App\Domains\Ambassador\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Referral extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'ambassador_referrals';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'ambassador_id',
        'code',
        'channel',
        'expires_at',
        'revoked_at',
        'last_used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function ambassador(): BelongsTo
    {
        return $this->belongsTo(Ambassador::class, 'ambassador_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(AmbassadorReferralUsage::class, 'referral_id');
    }
}
