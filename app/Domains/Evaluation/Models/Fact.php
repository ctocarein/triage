<?php

namespace App\Domains\Evaluation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Fact extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function mappings()
    {
        return $this->hasMany(FactMapping::class, 'fact_key', 'key');
    }

    public function riskSignals()
    {
        return $this->hasMany(RiskSignal::class, 'fact_key', 'key');
    }

    public function riskFlags()
    {
        return $this->hasMany(RiskFlag::class, 'fact_key', 'key');
    }
}