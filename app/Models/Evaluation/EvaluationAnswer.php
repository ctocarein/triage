<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Evaluation\EvaluationSession;
use App\Models\Evaluation\DynamicQuestionTemplate;

class EvaluationAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_session_id',
        'dynamic_question_template_id',
        'value',
        'is_skipped',
    ];

    protected $casts = [
        'value' => 'json',
        'is_skipped' => 'boolean',
    ];

    /**
     * La session d'évaluation liée
     */
    public function session()
    {
        return $this->belongsTo(EvaluationSession::class, 'evaluation_session_id');
    }

    /**
     * La question à laquelle cette réponse correspond
     */
    public function question()
    {
        return $this->belongsTo(DynamicQuestionTemplate::class, 'dynamic_question_template_id');
    }

    /**
     * Vérifie si la réponse est vide ou sautée
     */
    public function isEmpty(): bool
    {
        return $this->is_skipped || is_null($this->value) || $this->value === '';
    }
}
