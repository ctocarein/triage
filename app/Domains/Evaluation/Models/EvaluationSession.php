<?php

namespace App\Domains\Evaluation\Models;

use App\Domains\Report\Models\Report;
use App\Domains\Identity\Infrastructure\Persistence\Models\Identity;
use App\Domains\Decision\Models\DecisionInsight;
use App\Domains\Decision\Models\DecisionPath;
use Illuminate\Database\Eloquent\Model;
use App\Domains\Evaluation\Models\EvaluationAnswer;
use App\Domains\Evaluation\Models\DynamicQuestionTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Domains\Engagement\Infrastructure\Persistence\Models\ScanEvent;
use Illuminate\Support\Str;
use App\Domains\ExternalApi\Evaluation\Domain\Events\EvaluationCompleted;

class EvaluationSession extends Model
{
    use HasFactory;
    protected $fillable = [
        'identity_id',
        'public_id',
        'status',
        'context',
        'score',
        'risk_level',
        'started_at',
        'completed_at',
        'expires_at',
        'referral_id',
        'current_question_id',
        'current_node_key',
        'is_completed',
        'type',
        'target_blocs',
        'visited_blocs',
        'decision_path_id',
        'decision_path_key',
        'decision_path_version',
        'catalog_version',
        'risk_model_version',
    ];

    protected $casts = [
        'context' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
        'score' => 'integer',
        'target_blocs' => 'array',
        'visited_blocs' => 'array',
        'catalog_version' => 'integer',
        'risk_model_version' => 'integer',
    ];


    protected $hidden = [
        'id',
        'identity_id',
    ];

    protected static function booted()
    {
        static::creating(function (self $session): void {
            if (empty($session->public_id)) {
                $session->public_id = (string) Str::uuid();
            }
        });
    }


    // Relations
    public function identity()
    {
        return $this->belongsTo(Identity::class, 'identity_id');
    }

    public function referral()
    {
        return $this->belongsTo(\App\Domains\Ambassador\Infrastructure\Persistence\Models\Referral::class, 'referral_id');
    }
    public function answers()
    {
        return $this->hasMany(EvaluationAnswer::class, 'evaluation_session_id');
    }
    public function report()
    {
        return $this->hasOne(Report::class, 'evaluation_session_id');
    }
    public function decisionInsights()
    {
        return $this->hasMany(DecisionInsight::class, 'evaluation_session_id');
    }
    public function latestDecisionInsight()
    {
        return $this->hasOne(DecisionInsight::class, 'evaluation_session_id')->latestOfMany();
    }
    public function questions()
    {
        return $this->belongsToMany(DynamicQuestionTemplate::class, 'evaluation_answers', 'evaluation_session_id', 'question_id');
    }
    public function scanEvents()
    {
        return $this->hasMany(ScanEvent::class);
    }
    public function currentQuestion()
    {
        return $this->belongsTo(DynamicQuestionTemplate::class, 'current_question_id');
    }
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($query) {
            $query->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeActive($query)
    {
        return $query->inProgress()->notExpired();
    }
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }
    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFullEvaluation($query)
    {
        return $query->where('type', 'complete');
    }
    public function scopeThematic($query)
    {
        return $query->where('type', 'thematic');
    }
    public function isFullEvaluation(): bool
    {
        return $this->type === 'complete';
    }
    public function isThematic(): bool
    {
        return $this->type === 'thematic';
    }

    public function decisionPath()
    {
        return $this->belongsTo(DecisionPath::class, 'decision_path_id');
    }

    public static function availableTypes(): array
    {
        return ['complete', 'thematic'];
    }

    /**
     * VAcrifie si un bloc a dAcjAÿ ActAc visitAc.
     */
    public function hasVisitedBloc(string $blocKey): bool
    {
        return in_array($blocKey, $this->visited_blocs ?? []);
    }

    /**
     * Ajoute un bloc Aÿ la liste des blocs visitAcs.
     */
    public function addVisitedBloc(string $blocKey): void
    {
        $visited = $this->visited_blocs ?? [];

        if (!in_array($blocKey, $visited)) {
            $visited[] = $blocKey;
            $this->visited_blocs = $visited;
            $this->save();
        }
    }

    /**
     * VAcrifie si un bloc fait partie des blocs ciblAcs (ou si tous les blocs sont autorisAcs).
     */
    public function isBlocTargeted(string $blocKey): bool
    {
        return is_null($this->target_blocs) || in_array($blocKey, $this->target_blocs);
    }

    /**
     * RAcinitialise la progression des blocs (utile pour relancer une Acvaluation).
     */
    public function resetBlocTracking(): void
    {
        $this->visited_blocs = [];
        $this->current_bloc = null;
        $this->save();
    }

    public function markCompleted(): void
    {
        if ($this->status === 'completed' && $this->is_completed) {
            return;
        }

        $this->update([
            'is_completed' => true,
            'status' => 'completed',
            'completed_at' => now(),
            'current_question_id' => null,
            'current_node_key' => null,
        ]);

        event(new EvaluationCompleted($this));
    }
}


