<?php

namespace App\Domains\Ambassador\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmbassadorLevel extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'ambassador_levels';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'ambassador_id',
        'level_code',
        'computed_at',
    ];

    protected $casts = [
        'computed_at' => 'datetime',
    ];

    public function ambassador(): BelongsTo
    {
        return $this->belongsTo(Ambassador::class, 'ambassador_id');
    }
}
