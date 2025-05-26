<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id', 'evaluation_session_id', 'score', 'status',
        'summary', 'recommendation_text', 'recommended_action', 'risk_level'
    ];

    protected $casts = [
        'score' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relations
    public function account() { return $this->belongsTo(Account::class); }
    public function evaluationSession() { return $this->belongsTo(EvaluationSession::class); }

    // Scopes
    public function scopeByRiskLevel($query, string $level) {
        return $query->where('risk_level', $level);
    }
}