<?php

namespace App\Domains\Evaluation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RiskModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function signals()
    {
        return $this->hasMany(RiskSignal::class);
    }

    public function flags()
    {
        return $this->hasMany(RiskFlag::class);
    }
}