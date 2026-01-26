<?php

namespace App\Domains\Evaluation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IdempotencyKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'scope',
        'request_hash',
        'response',
        'expires_at',
    ];

    protected $casts = [
        'response' => 'array',
        'expires_at' => 'datetime',
    ];
}
