<?php

namespace App\Domains\Report\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Evaluation\Models\EvaluationSession;
use App\Domains\Decision\Models\DecisionPath;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_session_id',
        'decision_path_id',
        'identity_id',
        'report_code',
        'title',
        'summary',
        'answers',
        'score',
        'max_score',
        'risk_level',
        'specialties',
        'recommendation'
    ];

    protected $casts = [
        'answers' => 'array',
        'specialties' => 'array',
        'summary' => 'array',
    ];


    /**
     * Lien vers le compte utilisateur concerné
     */
    public function identity()
    {
        return $this->belongsTo(Identity::class, 'identity_id');
    }

    /**
     * Lien vers la session d’évaluation associée
     */
    public function evaluationSession()
    {
        return $this->belongsTo(EvaluationSession::class);
    }

    /**
     * Lien vers le chemin de décision (optionnel)
     */
    public function decisionPath()
    {
        return $this->belongsTo(DecisionPath::class, 'decision_path_id');
    }

    /**
     * Scope : filtrer les rapports par niveau de risque
     */
    public function scopeByRiskLevel($query, string $level)
    {
        return $query->where('risk_level', $level);
    }

    /**
     * Scope : filtrer par compte utilisateur
     */
    public function scopeForIdentity($query, string $identityId)
    {
        return $query->where('identity_id', $identityId);
    }
}
