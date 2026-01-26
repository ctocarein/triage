<?php

namespace App\Domains\Evaluation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ModelVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'version',
        'description',
        'checksum',
        'metadata',
        'is_active',
        'released_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'released_at' => 'datetime',
    ];
}
