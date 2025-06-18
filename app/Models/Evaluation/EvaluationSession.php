<?php

namespace App\Models\Evaluation;

use App\Models\Report;
use App\Models\Account;
use Illuminate\Database\Eloquent\Model;
use App\Models\Evaluation\EvaluationAnswer;
use App\Models\Evaluation\DynamicQuestionTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationSession extends Model
{
    use HasFactory;
   protected $fillable = [
        'account_id',
        'status',
        'context',
        'score',
        'started_at',
        'completed_at',
        'current_question_id',
        'is_completed',
        'type',
        'target_blocs',
        'visited_blocs',
    ];

    protected $casts = [
        'context' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'score' => 'integer',
        'target_blocs' => 'array',
        'visited_blocs' => 'array',
    ];

    // Relations
    public function account() { return $this->belongsTo(Account::class); }
    public function answers() { return $this->hasMany(EvaluationAnswer::class); }
    public function report() { return $this->hasOne(Report::class); }
    public function questions() { return $this->belongsToMany(DynamicQuestionTemplate::class, 'evaluation_answers'); }
    public function currentQuestion(){ return $this->belongsTo(DynamicQuestionTemplate::class, 'current_question_id');}
    public function scopeInProgress($query){return $query->where('status', 'in_progress');}
    public function isInProgress(): bool{return $this->status === 'in_progress';}
    // Scopes
    public function scopeCompleted($query) {return $query->where('status', 'completed');}

    public function scopeFullEvaluation($query) { return $query->where('type', 'complete'); }
    public function scopeThematic($query) { return $query->where('type', 'thematic'); }
    public function isFullEvaluation(): bool { return $this->type === 'complete'; }
    public function isThematic(): bool { return $this->type === 'thematic'; }

    public static function availableTypes(): array { return ['complete', 'thematic']; }

        /**
     * Vérifie si un bloc a déjà été visité.
     */
    public function hasVisitedBloc(string $blocKey): bool
    {
        return in_array($blocKey, $this->visited_blocs ?? []);
    }

    /**
     * Ajoute un bloc à la liste des blocs visités.
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
     * Vérifie si un bloc fait partie des blocs ciblés (ou si tous les blocs sont autorisés).
     */
    public function isBlocTargeted(string $blocKey): bool
    {
        return is_null($this->target_blocs) || in_array($blocKey, $this->target_blocs);
    }

    /**
     * Réinitialise la progression des blocs (utile pour relancer une évaluation).
     */
    public function resetBlocTracking(): void
    {
        $this->visited_blocs = [];
        $this->current_bloc = null;
        $this->save();
    }

    public function markCompleted(): void
    {
        $this->update([
            'is_completed' => true,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }


}