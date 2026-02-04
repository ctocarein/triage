<?php

namespace App\Domains\ExternalApi\Integration\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationConfig extends Model
{
    protected $fillable = [
        'provider',
        'config_key',
        'config_value',
        'is_secret',
    ];
}


