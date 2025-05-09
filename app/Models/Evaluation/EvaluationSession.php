<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Account; // Assurez-vous que ce use existe si Account est en dehors du namespace Evaluation

class EvaluationSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'status',              // in_progress, completed, aborted, etc.
        'context',             // json contextuel (ex: device, localisation, triage_type)
        'score',               // score final de l’évaluation
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'context' => 'array',         // Ajouté pour auto-caster le JSON
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'score' => 'integer',         // Sécurité si null
    ];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function answers()
    {
        return $this->hasMany(EvaluationAnswer::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }
}
