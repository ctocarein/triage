<?php

namespace App\Domains\Evaluation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RiskFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'risk_model_id',
        'model_version_id',
        'fact_key',
        'type',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function riskModel()
    {
        return $this->belongsTo(RiskModel::class);
    }

    public function modelVersion()
    {
        return $this->belongsTo(ModelVersion::class, 'model_version_id');
    }

    public function fact()
    {
        return $this->belongsTo(Fact::class, 'fact_key', 'key');
    }
}