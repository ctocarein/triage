<?php

namespace App\Domains\Decision\Models;

use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Report\Models\Report;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DecisionInsight extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_session_id',
        'report_id',
        'identity_id',
        'model_version',
        'calibration_tag',
        'risk_score',
        'risk_level',
        'score_total',
        'confidence_score',
        'confidence_interval_low',
        'confidence_interval_high',
        'data_quality',
        'explanations',
        'suggested_action',
        'requires_human_validation',
        'human_validation_reason',
        'final_decision',
        'vnext_shadow',
    ];

    protected $casts = [
        'risk_score' => 'float',
        'score_total' => 'float',
        'confidence_score' => 'float',
        'confidence_interval_low' => 'float',
        'confidence_interval_high' => 'float',
        'data_quality' => 'array',
        'explanations' => 'array',
        'final_decision' => 'array',
        'vnext_shadow' => 'array',
        'requires_human_validation' => 'boolean',
    ];

    public function session()
    {
        return $this->belongsTo(EvaluationSession::class, 'evaluation_session_id');
    }

    public function report()
    {
        return $this->belongsTo(Report::class, 'report_id');
    }

    public function identity()
    {
        return $this->belongsTo(Identity::class, 'identity_id');
    }
}
