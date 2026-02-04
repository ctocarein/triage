<?php

namespace App\Domains\Ambassador\Infrastructure\Persistence\Models;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ambassador extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'ambassadors';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'status',
        'activated_at',
        'deactivated_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    public function identity(): BelongsTo
    {
        return $this->belongsTo(Identity::class, 'user_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'ambassador_id');
    }

    public function badges(): HasMany
    {
        return $this->hasMany(AmbassadorBadge::class, 'ambassador_id');
    }

    public function level(): HasOne
    {
        return $this->hasOne(AmbassadorLevel::class, 'ambassador_id');
    }

    public function referralUsages(): HasManyThrough
    {
        return $this->hasManyThrough(
            AmbassadorReferralUsage::class,
            Referral::class,
            'ambassador_id',
            'referral_id',
            'id',
            'id'
        );
    }
}
