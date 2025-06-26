<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Account;
use App\Models\Evaluation\EvaluationSession;
use App\Models\DecisionPath;

class Report extends Model
{
    use HasFactory;

 protected $fillable = [
        'evaluation_session_id', 'decision_path_id', 'account_id',
        'report_code', 'title', 'summary', 'answers',
        'score', 'max_score', 'risk_level', 'specialties', 'recommendation'
    ];

    protected $casts = [
        'answers' => 'array',
        'specialties' => 'array',
    ];


    /**
     * Lien vers le compte utilisateur concerné
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
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
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }
}
