<?php

namespace App\Domains\Engagement\Infrastructure\Persistence\Models;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Organization\Models\Organization;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QrCode extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * The primary key is a UUID string.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'type',
        'code',
        'url',
        'campaign_id',
        'status',
        'activated_at',
        'scanned_at',
        'expired_at',
        'revoked_at',
        'metadata',
        'created_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'activated_at' => 'datetime',
        'scanned_at' => 'datetime',
        'expired_at' => 'datetime',
        'revoked_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Identity::class, 'created_by');
    }

    public function scanEvents(): HasMany
    {
        return $this->hasMany(ScanEvent::class);
    }
}
