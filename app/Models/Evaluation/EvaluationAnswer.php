<?php

namespace App\Models\Evaluation;

use Illuminate\Database\Eloquent\Model;
use App\Models\Evaluation\EvaluationSession;
use App\Models\Evaluation\DynamicQuestionTemplate;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EvaluationAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_session_id', 'dynamic_question_template_id', 'value', 'is_skipped'
    ];

    protected $casts = [
        'value' => 'array', 
        'is_skipped' => 'boolean',
    ];

    // Relations
    public function session() { return $this->belongsTo(EvaluationSession::class); }
    public function question() {  return $this->belongsTo(DynamicQuestionTemplate::class, 'dynamic_question_template_id'); }
    

    // Helpers
    public function isEmpty(): bool {
        return $this->is_skipped || empty($this->value);
    }
}