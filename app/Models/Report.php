<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;


class Report extends Model
{
    use HasFactory;
    protected $fillable = [
        'account_id',
        'evaluation_session_id',
        'score',
        'status',               // e.g. completed, reviewed, escalated
        'summary',              // phrase type : "Rassurant", "À consulter"
        'recommendation_text',  // Long texte avec recommandations
        'recommended_action',   // e.g. "visit_clinic", "wait_and_see", "repeat_test"
        'risk_level',           // enum: low / moderate / high
    ];

    protected $casts = [
        'score' => 'integer',
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * La session d'évaluation associée au rapport.
     */
    public function evaluationSession()
    {
        return $this->belongsTo(EvaluationSession::class);
    }
}
